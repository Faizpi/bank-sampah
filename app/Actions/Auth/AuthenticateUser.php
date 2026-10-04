<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\LoginRateLimiter;
use App\Support\Auth\PhoneNumber;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuthenticateUser
{
    private const string FAILURE_MESSAGE = 'Kredensial tidak valid atau akun tidak dapat digunakan.';

    public function __construct(
        private AuthFactory $auth,
        private Session $session,
        private LoginRateLimiter $limiter,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(string $phone, string $password, Request $request): User
    {
        $normalizedPhone = PhoneNumber::normalize($phone);
        $ip = $request->ip() ?? 'unknown';
        $correlationId = $this->correlationId($request);

        try {
            $this->limiter->ensureAllowed($normalizedPhone, $ip);
        } catch (ValidationException $exception) {
            $this->recordRejectedAttempt($request, $correlationId, 'rate_limited');

            throw $exception;
        }

        $guard = $this->auth->guard();

        if (! $guard->attempt([
            'phone' => $normalizedPhone,
            'password' => $password,
            'status' => UserStatus::Active->value,
        ])) {
            $this->limiter->recordFailure($normalizedPhone, $ip);
            $this->recordRejectedAttempt($request, $correlationId, 'invalid_credentials');

            throw ValidationException::withMessages(['phone' => self::FAILURE_MESSAGE]);
        }

        $this->session->regenerate();
        $user = $guard->user();

        if (! $user instanceof User) {
            $guard->logout();
            $this->session->invalidate();
            $this->session->regenerateToken();

            throw ValidationException::withMessages(['phone' => self::FAILURE_MESSAGE]);
        }

        try {
            return DB::transaction(function () use ($user, $correlationId): User {
                $user->forceFill(['last_login_at' => now()])->save();
                $this->auditLogger->record($user, 'access.login.succeeded', $user, [], [], $correlationId);

                return $user;
            });
        } catch (\Throwable) {
            $guard->logout();
            $this->session->invalidate();
            $this->session->regenerateToken();

            throw ValidationException::withMessages(['phone' => self::FAILURE_MESSAGE]);
        }
    }

    private function recordRejectedAttempt(Request $request, string $correlationId, string $reason): void
    {
        $subject = new User;
        $subject->setAttribute($subject->getKeyName(), 0);

        $this->auditLogger->record(null, 'access.login.rejected', $subject, [], ['reason' => $reason], $correlationId);
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId)
            ? strtolower($correlationId)
            : (string) Str::uuid();
    }
}

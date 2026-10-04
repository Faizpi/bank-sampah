<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class LogoutUser
{
    public function __construct(
        private AuthFactory $auth,
        private Session $session,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, string $action = 'access.logout.succeeded'): void
    {
        $user = $this->auth->guard()->user();

        if ($user instanceof User) {
            $this->auditLogger->record($user, $action, $user, [], [], $this->correlationId($request));
        }

        $this->auth->guard()->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId)
            ? strtolower($correlationId)
            : (string) Str::uuid();
    }
}

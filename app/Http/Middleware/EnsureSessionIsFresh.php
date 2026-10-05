<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Auth\LogoutUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\SessionAccess;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureSessionIsFresh
{
    public const LAST_ACTIVITY_KEY = 'auth.last_activity_at';

    private const DEFAULT_IDLE_MINUTES = 30;

    public function __construct(private LogoutUser $logoutUser) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, int $idleMinutes = self::DEFAULT_IDLE_MINUTES): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::LAST_ACTIVITY_KEY);
        $expired = is_int($lastActivity) && now()->getTimestamp() - $lastActivity >= $idleMinutes * 60;
        $started = $request->session()->get(SessionAccess::STARTED_AT);
        $absoluteExpired = is_int($started) && now()->getTimestamp() - $started >= 8 * 60 * 60;
        $currentActor = $actor->fresh();
        $fingerprint = $currentActor instanceof User ? SessionAccess::fingerprint($currentActor) : null;
        $previous = $request->session()->get(SessionAccess::AUTHORIZATION);
        $accessChanged = ! $currentActor instanceof User || $currentActor->status !== UserStatus::Active
            || (is_string($previous) && $previous !== $fingerprint);

        if ($expired || $absoluteExpired || $accessChanged) {
            $reason = $accessChanged ? 'access.session.authorization_changed' : ($absoluteExpired ? 'access.session.absolute_expired' : 'access.session.idle_expired');
            $this->logoutUser->handle($request, $reason);

            $request->session()->flash('pwa_logout_confirmed', true);

            return new RedirectResponse(route('login'));
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, now()->getTimestamp());
        $request->session()->put(SessionAccess::STARTED_AT, is_int($started) ? $started : now()->getTimestamp());
        $request->session()->put(SessionAccess::AUTHORIZATION, $fingerprint);

        return $next($request);
    }
}

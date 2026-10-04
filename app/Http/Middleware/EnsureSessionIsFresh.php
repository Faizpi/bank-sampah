<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Auth\LogoutUser;
use Closure;
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
        if (! $request->user()) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::LAST_ACTIVITY_KEY);
        $expired = is_int($lastActivity) && now()->getTimestamp() - $lastActivity >= $idleMinutes * 60;

        if ($expired) {
            $this->logoutUser->handle($request, 'access.session.idle_expired');

            return to_route('login')->with('pwa_logout_confirmed', true);
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, now()->getTimestamp());

        return $next($request);
    }
}

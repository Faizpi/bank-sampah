<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless($request->user()?->hasRole($role), 403);

        return $next($request);
    }
}

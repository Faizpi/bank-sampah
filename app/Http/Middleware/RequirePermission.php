<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authorization\PermissionChecker;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequirePermission
{
    public function __construct(private PermissionChecker $permissions) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $this->permissions->allows($request->user(), $permission)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}

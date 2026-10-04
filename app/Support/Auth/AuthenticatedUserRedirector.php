<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Authorization\PermissionChecker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class AuthenticatedUserRedirector
{
    public function __construct(private PermissionChecker $permissions) {}

    public function dashboardUrl(?User $user = null): string
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return route('home');
        }

        foreach ($this->dashboardCandidates($user) as [$routeName, $permission]) {
            if ($this->permissions->allows($user, $permission)) {
                return route($routeName);
            }
        }

        return route('home');
    }

    public function authorizedIntendedUrl(?string $intendedUrl, Request $request, ?User $user = null): ?string
    {
        $user ??= auth()->user();

        if (! $user instanceof User || $intendedUrl === null) {
            return null;
        }

        try {
            $origin = parse_url($request->root());
            $intended = parse_url($intendedUrl);
        } catch (\ValueError) {
            return null;
        }

        if (! is_array($origin) || ! is_array($intended) || ! $this->hasSameOrigin($origin, $intended)) {
            return null;
        }

        $path = $intended['path'] ?? null;

        if (! is_string($path) || isset($intended['user']) || isset($intended['pass'])) {
            return null;
        }

        try {
            $route = RouteFacade::getRoutes()->match(Request::create($path, 'GET'));
        } catch (BadRequestException|MethodNotAllowedHttpException|NotFoundHttpException) {
            return null;
        }

        $middleware = $route->gatherMiddleware();

        if (! in_array('auth', $middleware, true)) {
            return null;
        }

        foreach ($middleware as $middlewareName) {
            if (! str_starts_with($middlewareName, 'permission:')) {
                continue;
            }

            $permission = substr($middlewareName, strlen('permission:'));

            if ($permission === '' || ! $this->permissions->allows($user, $permission)) {
                return null;
            }
        }

        return $intendedUrl;
    }

    /** @return list<array{0: string, 1: string}> */
    private function dashboardCandidates(User $user): array
    {
        $roleNames = $user->roles()->pluck('name')->all();

        if (in_array('bendahara', $roleNames, true)) {
            return [
                ['treasurer.dashboard', 'withdrawal.view'],
                ['officer.dashboard', 'user.view'],
                ['citizen.dashboard', 'profile.view'],
            ];
        }

        if (array_intersect(['admin', 'superadmin'], $roleNames) !== []) {
            return [
                ['filament.backoffice.home', 'backoffice.access'],
                ['officer.dashboard', 'user.view'],
                ['citizen.dashboard', 'profile.view'],
            ];
        }

        if (in_array('petugas', $roleNames, true)) {
            return [
                ['officer.dashboard', 'user.view'],
                ['treasurer.dashboard', 'withdrawal.view'],
                ['citizen.dashboard', 'profile.view'],
            ];
        }

        return [
            ['citizen.dashboard', 'profile.view'],
            ['officer.dashboard', 'user.view'],
            ['treasurer.dashboard', 'withdrawal.view'],
        ];
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int, path?: string, query?: string, user?: string, pass?: string, fragment?: string}  $origin
     * @param  array{scheme?: string, host?: string, port?: int, path?: string, query?: string, user?: string, pass?: string, fragment?: string}  $intended
     */
    private function hasSameOrigin(array $origin, array $intended): bool
    {
        $originScheme = strtolower((string) ($origin['scheme'] ?? ''));
        $intendedScheme = strtolower((string) ($intended['scheme'] ?? ''));
        $originHost = strtolower((string) ($origin['host'] ?? ''));
        $intendedHost = strtolower((string) ($intended['host'] ?? ''));

        return $originScheme === $intendedScheme
            && $originHost === $intendedHost
            && $this->effectivePort($originScheme, $origin['port'] ?? null) === $this->effectivePort($intendedScheme, $intended['port'] ?? null);
    }

    private function effectivePort(string $scheme, int|string|null $port): int
    {
        if (is_int($port)) {
            return $port;
        }

        return $scheme === 'https' ? 443 : 80;
    }
}

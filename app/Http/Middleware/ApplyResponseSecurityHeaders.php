<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

final class ApplyResponseSecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = "base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    private const PERMISSIONS_POLICY = 'accelerometer=(), autoplay=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()';

    private const REFERRER_POLICY = 'strict-origin-when-cross-origin';

    private const STRICT_TRANSPORT_SECURITY = 'max-age=31536000; includeSubDomains';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($request, $next($request));
    }

    public static function apply(Request $request, Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', self::REFERRER_POLICY);
        $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        $response->headers->set('Permissions-Policy', self::permissionsPolicy($request));

        if (config('app.env') === 'production' && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', self::STRICT_TRANSPORT_SECURITY);
        } else {
            $response->headers->remove('Strict-Transport-Security');
        }

        if (self::mustNotStore($request)) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    private static function permissionsPolicy(Request $request): string
    {
        if ($request->route()?->getName() === 'officer.customer-identification') {
            return str_replace('camera=()', 'camera=(self)', self::PERMISSIONS_POLICY);
        }

        return self::PERMISSIONS_POLICY;
    }

    private static function mustNotStore(Request $request): bool
    {
        if ($request->user() !== null || $request->is('login', 'daftar', 'media/*', 'laporan/ekspor/*')) {
            return true;
        }

        $route = $request->route();
        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (in_array(strtok($middleware, ':'), ['auth', 'permission', 'session.fresh'], true)) {
                return true;
            }
        }

        return false;
    }
}

<?php

use App\Http\Middleware\ApplyResponseSecurityHeaders;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Http\Middleware\RequirePermission;
use App\Support\Auth\AuthenticatedUserRedirector;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignCorrelationId::class);
        $middleware->append(ApplyResponseSecurityHeaders::class);
        $middleware->preventRequestsDuringMaintenance(['/health', '/operations/health']);
        $middleware->redirectUsersTo(fn (Request $request): string => app(AuthenticatedUserRedirector::class)->dashboardUrl());
        $middleware->alias([
            'permission' => RequirePermission::class,
            'session.fresh' => EnsureSessionIsFresh::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->respond(
            static fn (Response $response): Response => ApplyResponseSecurityHeaders::apply(request(), $response),
        );
    })->create();

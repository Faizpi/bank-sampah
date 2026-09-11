<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as LogContextRepository;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public function __construct(
        private readonly LogContextRepository $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->correlationIdFrom($request);

        $request->attributes->set('correlation_id', $correlationId);
        $this->context->add('correlation_id', $correlationId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    private function correlationIdFrom(Request $request): string
    {
        $provided = $request->headers->get(self::HEADER);

        if (is_string($provided) && Str::isUuid($provided)) {
            return strtolower($provided);
        }

        return (string) Str::uuid();
    }
}

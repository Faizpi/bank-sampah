<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\AssignCorrelationId;
use Tests\TestCase;

final class CorrelationIdTest extends TestCase
{
    public function test_response_receives_a_generated_correlation_id(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $response->headers->get(AssignCorrelationId::HEADER),
        );
    }

    public function test_valid_incoming_correlation_id_is_preserved(): void
    {
        $correlationId = '018f4ca4-2e67-7c16-a455-8f610f6f5642';

        $this->withHeader(AssignCorrelationId::HEADER, $correlationId)
            ->get(route('home'))
            ->assertHeader(AssignCorrelationId::HEADER, $correlationId);
    }

    public function test_invalid_incoming_correlation_id_is_replaced(): void
    {
        $response = $this->withHeader(AssignCorrelationId::HEADER, "invalid\r\nvalue")
            ->get(route('home'));

        $response->assertOk();
        self::assertNotSame('invalid value', $response->headers->get(AssignCorrelationId::HEADER));
    }
}

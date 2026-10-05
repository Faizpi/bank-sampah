<?php

namespace Tests\Feature;

use Tests\TestCase;

final class TestEnvironmentTest extends TestCase
{
    public function test_browser_preflight_requires_a_matching_test_token(): void
    {
        config()->set('app.test_environment_token', str_repeat('a', 32));
        $this->get('/_test/environment')->assertNotFound();
        $this->withHeader('X-Test-Environment', str_repeat('b', 32))->get('/_test/environment')->assertNotFound();
        $this->withHeader('X-Test-Environment', str_repeat('a', 32))->get('/_test/environment')
            ->assertOk()->assertJson(['environment' => 'testing', 'database' => ':memory:']);
    }
}

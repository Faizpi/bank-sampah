<?php

namespace Tests;

use App\Http\Middleware\EnsureSessionIsFresh;
use App\Support\Auth\SessionAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        // This helper starts a simulated authentication, not a replay of an existing browser session.
        session()->forget(SessionAccess::AUTHORIZATION);
        if (auth()->check() && auth()->id() !== $user->getAuthIdentifier()) {
            session()->forget([
                SessionAccess::STARTED_AT,
                SessionAccess::AUTHORIZATION,
                EnsureSessionIsFresh::LAST_ACTIVITY_KEY,
            ]);
        }

        return parent::actingAs($user, $guard);
    }

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $app->addAbsoluteCachePathPrefix(substr(sys_get_temp_dir(), 0, 3));
        $app->loadEnvironmentFrom('.env.testing');
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}

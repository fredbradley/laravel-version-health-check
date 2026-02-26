<?php

namespace FredBradley\LaravelVersionHealthCheck\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Health\HealthServiceProvider;
use FredBradley\LaravelVersionHealthCheck\ServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            HealthServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('health.result_stores', []);
    }
}

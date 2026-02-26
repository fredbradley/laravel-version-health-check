<?php

namespace FredBradley\LaravelVersionHealthCheck;

use Spatie\Health\Facades\Health;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot(): void
    {
        Health::checks([
            LaravelVersionHealthCheck::new()->name('Laravel Version')
        ]);
    }
}

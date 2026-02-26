<?php

use FredBradley\LaravelVersionHealthCheck\LaravelVersionHealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns ok when the app is running the latest Laravel version', function () {
    $currentVersion = app()->version();

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $currentVersion]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->shortSummary)->toBe('Up to date');
});

it('returns a warning when the app is behind the latest Laravel version', function () {
    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v99.0.0']),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Not the latest Laravel version');
});

it('strips the leading v prefix from the GitHub release tag before comparing', function () {
    $currentVersion = app()->version();

    // GitHub returns tags like "v12.0.0" — without ltrim this would never match
    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $currentVersion]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('ok');
});

it('returns a warning when a newer patch version is available', function () {
    $parts = explode('.', app()->version());
    $parts[2] = (int) ($parts[2] ?? 0) + 1;
    $newerPatchVersion = implode('.', $parts);

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $newerPatchVersion]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning');
});

it('caches the latest Laravel version so GitHub is only called once', function () {
    $currentVersion = app()->version();

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $currentVersion]),
    ]);

    // Run the check twice — the second call should hit the cache, not GitHub
    LaravelVersionHealthCheck::new()->run();
    LaravelVersionHealthCheck::new()->run();

    Http::assertSentCount(1);
});

it('stores the response under the correct cache key', function () {
    $currentVersion = app()->version();

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $currentVersion]),
    ]);

    LaravelVersionHealthCheck::new()->run();

    expect(Cache::get('laravel-version-latest'))->toBe('v' . $currentVersion);
});

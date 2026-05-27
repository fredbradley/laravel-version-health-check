<?php

use Carbon\Carbon;
use FredBradley\LaravelVersionHealthCheck\LaravelVersionHealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::forget('laravel-version-latest');
    Cache::forget('laravel-end-of-active-support');
});

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
    $currentMajor = (int) explode('.', app()->version())[0];

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v99.0.0']),
        'endoflife.date/*' => Http::response([
            'releases' => [
                ['name' => $currentMajor, 'eoasFrom' => '2099-01-01'],
            ],
        ]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Running '.app()->version());
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

it('returns ok when a newer patch version is available but the major version matches', function () {
    $parts = explode('.', app()->version());
    $parts[2] = (int) ($parts[2] ?? 0) + 1;
    $newerPatchVersion = implode('.', $parts);

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $newerPatchVersion]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('ok');
});

it('returns a warning when the latest major version is different', function () {
    $parts = explode('.', app()->version());
    $currentMajor = (int) $parts[0];
    $parts[0] = $currentMajor + 1;
    $newerMajorVersion = implode('.', $parts);

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $newerMajorVersion]),
        'endoflife.date/*' => Http::response([
            'releases' => [
                ['name' => $currentMajor, 'eoasFrom' => '2099-01-01'],
            ],
        ]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Running '.app()->version());
});

it('returns a warning with the EOAS date when active support ends within 5 days', function () {
    $parts = explode('.', app()->version());
    $currentMajor = (int) $parts[0];
    $parts[0] = $currentMajor + 1;
    $newerMajorVersion = implode('.', $parts);
    $endingSoon = Carbon::now()->addDays(3)->format('Y-m-d');

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $newerMajorVersion]),
        'endoflife.date/*' => Http::response([
            'releases' => [
                ['name' => $currentMajor, 'eoasFrom' => $endingSoon],
            ],
        ]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toContain('Active support ends');
});

it('returns failed when active support has already ended', function () {
    $parts = explode('.', app()->version());
    $currentMajor = (int) $parts[0];
    $parts[0] = $currentMajor + 1;
    $newerMajorVersion = implode('.', $parts);

    Http::fake([
        'api.github.com/*' => Http::response(['name' => 'v' . $newerMajorVersion]),
        'endoflife.date/*' => Http::response([
            'releases' => [
                ['name' => $currentMajor, 'eoasFrom' => '2000-01-01'],
            ],
        ]),
    ]);

    $result = LaravelVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('failed')
        ->and($result->notificationMessage)->toBe('No longer supported');
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

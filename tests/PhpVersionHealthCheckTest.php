<?php

use Carbon\Carbon;
use FredBradley\LaravelVersionHealthCheck\PhpVersionHealthCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function phpCycle(): string
{
    return implode('.', array_slice(explode('.', PHP_VERSION), 0, 2));
}

function newerPhpPatchVersion(): string
{
    $parts = explode('.', PHP_VERSION);
    $parts[2] = ((int) ($parts[2] ?? 0)) + 1;

    return implode('.', array_slice($parts, 0, 3));
}

function newerPhpCycle(): string
{
    [$major, $minor] = array_map('intval', explode('.', phpCycle()));

    return $major.'.'.($minor + 1);
}

beforeEach(function () {
    Cache::forget('php-version-releases');
});

it('returns ok when running the latest PHP release', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => phpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => PHP_VERSION]],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->shortSummary)->toBe('Up to date');
});

it('returns ok when on the latest cycle but an older patch version', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => phpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => newerPhpPatchVersion()]],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('ok')
        ->and($result->shortSummary)->toBe('Running '.PHP_VERSION);
});

it('returns a warning when behind the latest cycle but support is not ending soon', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => newerPhpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => newerPhpCycle().'.0']],
                    ['name' => phpCycle(), 'eoasFrom' => '2099-01-01'],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Running '.PHP_VERSION);
});

it('returns failed with the EOAS date when active support ends within 7 days', function () {
    $endingSoon = Carbon::now()->addDays(3)->format('Y-m-d');

    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => newerPhpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => newerPhpCycle().'.0']],
                    ['name' => phpCycle(), 'eoasFrom' => $endingSoon],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('failed')
        ->and($result->notificationMessage)->toContain('Active support ends');
});

it('returns failed when active support has already ended', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => newerPhpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => newerPhpCycle().'.0']],
                    ['name' => phpCycle(), 'eoasFrom' => '2000-01-01'],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('failed')
        ->and($result->notificationMessage)->toBe('No longer supported');
});

it('returns a warning with unknown support status when the current cycle is absent from endoflife data', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => newerPhpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => newerPhpCycle().'.0']],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Running '.PHP_VERSION.' (support status unknown)');
});

it('returns a warning with unknown support status when eoasFrom is missing', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => newerPhpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => newerPhpCycle().'.0']],
                    ['name' => phpCycle()],
                ],
            ],
        ]),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Running '.PHP_VERSION.' (support status unknown)');
});

it('returns a warning when the endoflife API is unavailable', function () {
    Http::fake([
        'endoflife.date/*' => Http::response(null, 500),
    ]);

    $result = PhpVersionHealthCheck::new()->run();

    expect($result->status->value)->toBe('warning')
        ->and($result->notificationMessage)->toBe('Could not fetch PHP release data');
});

it('caches the endoflife release data so the API is only called once', function () {
    Http::fake([
        'endoflife.date/*' => Http::response([
            'result' => [
                'releases' => [
                    ['name' => phpCycle(), 'eoasFrom' => '2099-01-01', 'latest' => ['name' => PHP_VERSION]],
                ],
            ],
        ]),
    ]);

    PhpVersionHealthCheck::new()->run();
    PhpVersionHealthCheck::new()->run();

    Http::assertSentCount(1);
});
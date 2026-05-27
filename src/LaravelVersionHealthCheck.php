<?php

namespace FredBradley\LaravelVersionHealthCheck;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class LaravelVersionHealthCheck extends Check
{
    private function getLatestLaravelVersion(): ?string
    {
        return Cache::remember('laravel-version-latest', Carbon::now()->addHours(6), function () {
            $response = Http::baseUrl('https://api.github.com')
                ->get('repos/laravel/framework/releases/latest');

            return $response->successful() ? $response->json('name') : null;
        });
    }

    private function getLaravelEndOfActiveSupport(int $version): ?CarbonInterface
    {
        $data = Cache::remember('laravel-end-of-active-support', Carbon::now()->addDay(), function () {
            $response = Http::baseUrl('https://endoflife.date/api/v1/products/')
                ->get('laravel');

            return $response->successful() ? $response->json('result') : null;
        });

        if (!is_array($data) || !isset($data['releases'])) {
            return null;
        }

        $release = collect($data['releases'])->where('name', (string) $version)->first();

        if (!$release || !isset($release['eoasFrom'])) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $release['eoasFrom']);
    }

    private function getEnvironmentData(): ?array
    {
        Artisan::call('about', [
            '--only' => 'Environment',
            '--json' => true,
        ]);

        return json_decode(Artisan::output(), true);
    }

    public function run(): Result
    {
        $result = Result::make();

        $rawLatest = $this->getLatestLaravelVersion();
        if ($rawLatest === null) {
            return $result->warning('Could not fetch latest Laravel version');
        }

        $envData = $this->getEnvironmentData();
        if (!is_array($envData) || !isset($envData['environment']['laravel_version'])) {
            return $result->warning('Could not determine current Laravel version');
        }

        $latestVersion = ltrim($rawLatest, 'v');
        $currentVersion = $envData['environment']['laravel_version'];
        if ($latestVersion === $currentVersion) {
            $result->shortSummary('Up to date');

            return $result->ok();
        }

        $latestMajor = (int) explode('.', $latestVersion)[0];
        $currentMajor = (int) explode('.', $currentVersion)[0];

        if ($latestMajor !== $currentMajor) {
            $eoas = $this->getLaravelEndOfActiveSupport($currentMajor);

            if ($eoas === null) {
                return $result->warning('Running '.$currentVersion.' (support status unknown)');
            }

            if ($eoas->isPast()) {
                return $result->failed('No longer supported');
            }

            if ($eoas->lte(Carbon::now()->addDays(7))) {
                return $result->failed('Active support ends '.$eoas->toFormattedDateString());
            }

            return $result->warning('Running '.$currentVersion);
        }

        $result->shortSummary('Running '.$currentVersion);

        return $result->ok();
    }
}

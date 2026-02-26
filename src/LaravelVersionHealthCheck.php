<?php

namespace FredBradley\LaravelVersionHealthCheck;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class LaravelVersionHealthCheck extends Check
{
    private function getLatestLaravelVersion(): string
    {
        return Cache::remember('laravel-version-latest', Carbon::now()->addHours(6), function () {
            return Http::get('https://api.github.com/repos/laravel/framework/releases/latest')
                ->json('name');
        });
    }

    /**
     * @return array{
     *     environment: array{
     *         application_name: string,
     *         laravel_version: string,
     *         php_version: string,
     *         composer_version: string,
     *         environment: string,
     *         debug_mode: bool,
     *         url: string,
     *         maintenance_mode: bool,
     *         timezone: string,
     *         locale: string
     *     }
     * }
     */
    private function getEnvironmentData(): array
    {
        Artisan::call('about', [
            '--only' => 'Environment',
            '--json' => true,
        ]);

        return json_decode(Artisan::output(), true);
    }

    public function run(): Result
    {
        $latestVersion = ltrim($this->getLatestLaravelVersion(), 'v');
        $envData = $this->getEnvironmentData();
        $result = Result::make();

        if ($latestVersion === $envData['environment']['laravel_version']) {
            $result->shortSummary('Up to date');

            return $result->ok();
        }

        return $result->warning('Not the latest Laravel version');
    }
}

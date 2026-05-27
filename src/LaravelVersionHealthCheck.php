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
    private function getLatestLaravelVersion(): string
    {
        return Cache::remember('laravel-version-latest', Carbon::now()->addHours(6), function () {
            return Http::get('https://api.github.com/repos/laravel/framework/releases/latest')
                ->json('name');
        });
    }
    private function getLaravelEndOfActiveSupport($version): CarbonInterface
    {
        $data = Cache::remember('laravel-end-of-active-support', Carbon::now()->addDay(), function () {
            return Http::get('https://endoflife.date/api/v1/products/laravel')
                ->json();
        });

        $releases = collect($data['releases']);
        $eoasDate = $releases->where('name', $version)->first()['eoasFrom'];
        return Carbon::createFromFormat('Y-m-d', $eoasDate);
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
        $currentVersion = $envData['environment']['laravel_version'];
        $result = Result::make();

        if ($latestVersion === $currentVersion) {
            $result->shortSummary('Up to date');

            return $result->ok();
        }

        $latestMajor = (int) explode('.', $latestVersion)[0];
        $currentMajor = (int) explode('.', $currentVersion)[0];

        if ($latestMajor !== $currentMajor) {
            $eoas = $this->getLaravelEndOfActiveSupport($currentMajor);

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

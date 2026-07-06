<?php

namespace FredBradley\LaravelVersionHealthCheck;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PhpVersionHealthCheck extends Check
{
    protected function getCurrentVersion(): string
    {
        return PHP_VERSION;
    }

    private function getPhpReleases(): ?array
    {
        return Cache::remember('php-version-releases', Carbon::now()->addHours(6), function () {
            $response = Http::baseUrl('https://endoflife.date/api/v1/products/')
                ->get('php');

            return $response->successful() ? $response->json('result.releases') : null;
        });
    }

    public function run(): Result
    {
        $result = Result::make();

        $releases = $this->getPhpReleases();
        if (!is_array($releases) || empty($releases)) {
            return $result->warning('Could not fetch PHP release data');
        }

        $currentVersion = $this->getCurrentVersion();
        $currentCycle = implode('.', array_slice(explode('.', $currentVersion), 0, 2));

        // endoflife.date returns releases with the newest cycle first.
        $latestRelease = $releases[0];

        if (($latestRelease['latest']['name'] ?? null) === $currentVersion) {
            $result->shortSummary('Up to date');

            return $result->ok();
        }

        if (($latestRelease['name'] ?? null) === $currentCycle) {
            $result->shortSummary('Running '.$currentVersion);

            return $result->ok();
        }

        $release = collect($releases)->where('name', $currentCycle)->first();

        if (!$release || !isset($release['eoasFrom'])) {
            return $result->warning('Running '.$currentVersion.' (support status unknown)');
        }

        $eoas = Carbon::createFromFormat('Y-m-d', $release['eoasFrom']);

        if ($eoas->isPast()) {
            return $result->failed('No longer supported');
        }

        if ($eoas->lte(Carbon::now()->addDays(7))) {
            return $result->failed('Active support ends '.$eoas->toFormattedDateString());
        }

        return $result->warning('Running '.$currentVersion);
    }
}
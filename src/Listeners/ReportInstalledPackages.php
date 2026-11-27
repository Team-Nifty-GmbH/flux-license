<?php

namespace TeamNiftyGmbH\FluxLicense\Listeners;

use FluxErp\Settings\CoreSettings;
use Illuminate\Contracts\Database\Events\MigrationEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TeamNiftyGmbH\FluxLicense\Support\InstalledPackages;
use Throwable;

// Tells the license server what is installed, so the store only offers agents this instance can run
class ReportInstalledPackages
{
    public function handle(MigrationEvent $event): void
    {
        // A deploy must never fail on this, the next one retries. Settings and cache can throw too
        // (missing table after a rollback or reset), so everything stays inside the try
        try {
            // Only real up runs on production, no dev machine posts under a customer key
            if ($event->method !== 'up'
                || data_get($event, 'options.pretend')
                || ! app()->environment('production')
            ) {
                return;
            }

            $licenseKey = app(CoreSettings::class)->license_key;
            $lockFile = base_path('composer.lock');

            if (blank($licenseKey) || ! file_exists($lockFile)) {
                return;
            }

            $hash = md5_file($lockFile);
            // The key is part of the cache key, so a changed license key reports again
            $cacheKey = 'flux-license.packages-hash.' . md5((string) $licenseKey);

            if (Cache::get($cacheKey) === $hash) {
                return;
            }

            $response = Http::timeout(5)->post(
                'https://flux.team-nifty.com/api/flux-licenses/' . $licenseKey . '/packages',
                ['packages' => InstalledPackages::all()]
            );

            if ($response->successful()) {
                Cache::forever($cacheKey, $hash);
            } else {
                Log::warning('flux-license: reporting installed packages failed', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

<?php

namespace TeamNiftyGmbH\FluxLicense\Listeners;

use FluxErp\Settings\CoreSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use TeamNiftyGmbH\FluxLicense\Support\InstalledPackages;
use Throwable;

// Tells the license server what is installed, so the store only offers agents this instance can run
class ReportInstalledPackages
{
    public function handle(): void
    {
        $licenseKey = app(CoreSettings::class)->license_key;
        $lockFile = base_path('composer.lock');

        if (blank($licenseKey) || ! file_exists($lockFile)) {
            return;
        }

        $hash = md5_file($lockFile);

        if (Cache::get('flux-license.packages-hash') === $hash) {
            return;
        }

        // A deploy must never fail on this, the next one retries
        try {
            $response = Http::timeout(10)->post(
                'https://flux.team-nifty.com/api/flux-licenses/' . $licenseKey . '/packages',
                ['packages' => InstalledPackages::all()]
            );

            if ($response->successful()) {
                Cache::forever('flux-license.packages-hash', $hash);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

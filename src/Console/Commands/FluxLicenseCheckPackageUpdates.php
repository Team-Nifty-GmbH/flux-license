<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use Illuminate\Console\Command;

class FluxLicenseCheckPackageUpdates extends Command
{
    protected $description = 'Notify Super Admins about available updates for packages from packages.nuxbe.io';

    protected $signature = 'flux-license:check-package-updates';

    public function handle(): int
    {
        return self::SUCCESS;
    }

    public function getNuxbePackagesFromLock(): array
    {
        $lockPath = base_path('composer.lock');

        if (! file_exists($lockPath)) {
            return [];
        }

        $lock = json_decode(file_get_contents($lockPath), true) ?: [];

        $packages = array_merge(
            data_get($lock, 'packages', []),
            data_get($lock, 'packages-dev', []),
        );

        $result = [];
        foreach ($packages as $package) {
            $notificationUrl = (string) data_get($package, 'notification-url', '');

            if (! str_starts_with($notificationUrl, 'https://packages.nuxbe.io/')) {
                continue;
            }

            $result[data_get($package, 'name')] = ltrim((string) data_get($package, 'version'), 'v');
        }

        return $result;
    }
}

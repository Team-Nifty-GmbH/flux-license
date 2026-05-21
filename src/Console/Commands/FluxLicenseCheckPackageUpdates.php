<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use Composer\Semver\Comparator;
use FluxErp\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use TeamNiftyGmbH\FluxLicense\Notifications\PackageUpdatesAvailable;
use TeamNiftyGmbH\FluxLicense\Support\NuxbePackagesClient;

class FluxLicenseCheckPackageUpdates extends Command
{
    protected $description = 'Notify Super Admins about available updates for packages from packages.nuxbe.io';

    protected $signature = 'flux-license:check-package-updates';

    public function handle(NuxbePackagesClient $client): int
    {
        $installed = $this->getNuxbePackagesFromLock();

        if ($installed === []) {
            return self::SUCCESS;
        }

        $latest = $client->latestVersions(array_keys($installed));

        $updates = [];
        foreach ($installed as $name => $current) {
            $available = $latest[$name] ?? null;

            if ($available === null) {
                continue;
            }

            if (! Comparator::greaterThan($available, $current)) {
                continue;
            }

            $updates[] = [
                'name' => $name,
                'current' => $current,
                'available' => $available,
            ];
        }

        if ($updates === []) {
            return self::SUCCESS;
        }

        $admins = User::role('Super Admin')->get();

        if ($admins->isEmpty()) {
            return self::SUCCESS;
        }

        Notification::send($admins, new PackageUpdatesAvailable($updates));

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

<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use Composer\Semver\Comparator;
use FluxErp\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use TeamNiftyGmbH\FluxLicense\Notifications\PackageUpdatesAvailable;
use TeamNiftyGmbH\FluxLicense\Support\NuxbePackagesClient;
use Throwable;

class FluxLicenseCheckPackageUpdates extends Command
{
    protected $description = 'Notify Super Admins about available updates for packages from packages.nuxbe.io';

    protected $signature = 'flux-license:check-package-updates';

    public function handle(NuxbePackagesClient $client): int
    {
        if (! file_exists(base_path('composer.lock'))) {
            Log::error('flux-license:check-package-updates: composer.lock not found');
            $this->error('composer.lock not found');

            return self::FAILURE;
        }

        $installed = $this->getNuxbePackagesFromLock();

        if ($installed === []) {
            return self::SUCCESS;
        }

        try {
            $latest = $client->latestVersions(array_keys($installed));
        } catch (Throwable $e) {
            Log::error('flux-license:check-package-updates: failed to fetch packages.json', [
                'exception' => $e->getMessage(),
            ]);
            $this->error('Failed to fetch packages.json: ' . $e->getMessage());

            return self::FAILURE;
        }

        $updates = [];
        foreach ($installed as $name => $current) {
            $available = $latest[$name] ?? null;

            if ($available === null) {
                continue;
            }

            if (! Comparator::greaterThan($available, $current)) {
                continue;
            }

            if (Cache::get($this->cacheKey($name)) === $available) {
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

        foreach ($updates as $update) {
            Cache::forever($this->cacheKey($update['name']), $update['available']);
        }

        return self::SUCCESS;
    }

    protected function cacheKey(string $packageName): string
    {
        return 'flux-license:last-notified-update:' . $packageName;
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

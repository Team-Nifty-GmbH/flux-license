<?php

namespace TeamNiftyGmbH\FluxLicense\Support;

class InstalledPackages
{
    public static function all(): array
    {
        $lockFile = base_path('composer.lock');

        if (! file_exists($lockFile)) {
            return [];
        }

        $lock = json_decode(file_get_contents($lockFile), true);

        return collect(data_get($lock, 'packages', []))
            ->filter(fn (array $package) => str_starts_with($package['name'], 'team-nifty-gmbh/')
                || str_starts_with($package['name'], 'laravel/')
                || str_starts_with($package['name'], 'livewire/')
                || str_starts_with($package['name'], 'tallstackui/')
                || str_starts_with($package['name'], 'saloonphp/')
                || str_starts_with($package['name'], 'spatie/')
            )
            ->mapWithKeys(fn (array $package) => [
                $package['name'] => [
                    'version' => $package['version'],
                ],
            ])
            ->sortKeys()
            ->toArray();
    }
}

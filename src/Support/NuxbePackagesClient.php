<?php

namespace TeamNiftyGmbH\FluxLicense\Support;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Http;

class NuxbePackagesClient
{
    public const REPOSITORY_URL = 'https://packages.nuxbe.io';

    public function latestVersions(array $packageNames): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get(self::REPOSITORY_URL . '/packages.json')
            ->throw();

        $packages = data_get($response->json(), 'packages', []);

        $result = [];
        foreach ($packageNames as $name) {
            $versions = array_keys($packages[$name] ?? []);

            $stable = array_filter(
                $versions,
                fn (string $version): bool => VersionParser::parseStability($version) === 'stable',
            );

            if ($stable === []) {
                continue;
            }

            $sorted = Semver::rsort($stable);
            $result[$name] = ltrim($sorted[0], 'v');
        }

        return $result;
    }
}

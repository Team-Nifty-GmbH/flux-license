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
        $packages = $this->loadAllPackages();

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

    protected function loadAllPackages(): array
    {
        $root = $this->fetchJson(self::REPOSITORY_URL . '/packages.json');

        $packages = data_get($root, 'packages', []);

        foreach (array_keys(data_get($root, 'includes', [])) as $relativeUrl) {
            $included = $this->fetchJson(self::REPOSITORY_URL . '/' . ltrim($relativeUrl, '/'));
            $packages = array_merge_recursive($packages, data_get($included, 'packages', []));
        }

        return $packages;
    }

    protected function fetchJson(string $url): array
    {
        return Http::timeout(10)
            ->acceptJson()
            ->get($url)
            ->throw()
            ->json() ?? [];
    }
}

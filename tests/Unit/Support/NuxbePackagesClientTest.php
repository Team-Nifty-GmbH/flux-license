<?php

use Illuminate\Support\Facades\Http;
use TeamNiftyGmbH\FluxLicense\Support\NuxbePackagesClient;

test('returns highest stable version for each requested package', function (): void {
    Http::fake([
        'packages.nuxbe.io/packages.json' => Http::response([
            'packages' => [
                'team-nifty-gmbh/flux-erp' => [
                    '1.0.0' => ['name' => 'team-nifty-gmbh/flux-erp', 'version' => '1.0.0'],
                    '1.2.0' => ['name' => 'team-nifty-gmbh/flux-erp', 'version' => '1.2.0'],
                    '1.1.5' => ['name' => 'team-nifty-gmbh/flux-erp', 'version' => '1.1.5'],
                ],
                'team-nifty-gmbh/flux-license' => [
                    '0.9.0' => ['name' => 'team-nifty-gmbh/flux-license', 'version' => '0.9.0'],
                ],
            ],
        ], 200),
    ]);

    $result = app(NuxbePackagesClient::class)->latestVersions([
        'team-nifty-gmbh/flux-erp',
        'team-nifty-gmbh/flux-license',
    ]);

    expect($result)->toBe([
        'team-nifty-gmbh/flux-erp' => '1.2.0',
        'team-nifty-gmbh/flux-license' => '0.9.0',
    ]);
});

test('follows Satis includes to find packages', function (): void {
    Http::fake([
        'packages.nuxbe.io/packages.json' => Http::response([
            'includes' => [
                'include/all$abc123.json' => ['sha1' => 'abc123'],
            ],
        ], 200),
        'packages.nuxbe.io/include/all$abc123.json' => Http::response([
            'packages' => [
                'team-nifty-gmbh/flux-erp' => [
                    '2.0.0' => ['name' => 'team-nifty-gmbh/flux-erp', 'version' => '2.0.0'],
                ],
            ],
        ], 200),
    ]);

    $result = app(NuxbePackagesClient::class)->latestVersions(['team-nifty-gmbh/flux-erp']);

    expect($result)->toBe(['team-nifty-gmbh/flux-erp' => '2.0.0']);
});

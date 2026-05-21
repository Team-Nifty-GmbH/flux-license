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

<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use TeamNiftyGmbH\FluxLicense\Console\Commands\FluxLicenseCheckPackageUpdates;

beforeEach(function (): void {
    // Write a controlled composer.lock fixture into the testbench base_path.
    $this->originalLock = File::exists(base_path('composer.lock'))
        ? File::get(base_path('composer.lock'))
        : null;

    File::put(base_path('composer.lock'), json_encode([
        'packages' => [
            [
                'name' => 'team-nifty-gmbh/flux-erp',
                'version' => '1.0.0',
                'notification-url' => 'https://packages.nuxbe.io/downloads/',
            ],
            [
                'name' => 'symfony/console',
                'version' => '7.0.0',
                'notification-url' => 'https://packagist.org/downloads/',
            ],
        ],
        'packages-dev' => [
            [
                'name' => 'team-nifty-gmbh/flux-dev-tools',
                'version' => '0.5.0',
                'notification-url' => 'https://packages.nuxbe.io/downloads/',
            ],
        ],
    ]));
});

afterEach(function (): void {
    if ($this->originalLock !== null) {
        File::put(base_path('composer.lock'), $this->originalLock);
    } else {
        File::delete(base_path('composer.lock'));
    }
});

test('extracts only packages whose notification-url points to packages.nuxbe.io', function (): void {
    Http::fake(['packages.nuxbe.io/*' => Http::response(['packages' => []], 200)]);

    $command = app(FluxLicenseCheckPackageUpdates::class);

    expect($command->getNuxbePackagesFromLock())->toBe([
        'team-nifty-gmbh/flux-erp' => '1.0.0',
        'team-nifty-gmbh/flux-dev-tools' => '0.5.0',
    ]);
});

<?php

use FluxErp\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use FluxErp\Models\Role;
use TeamNiftyGmbH\FluxLicense\Console\Commands\FluxLicenseCheckPackageUpdates;
use TeamNiftyGmbH\FluxLicense\Notifications\PackageUpdatesAvailable;

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

test('sends a notification to all super admins when nuxbe packages have updates', function (): void {
    Notification::fake();

    Http::fake([
        'packages.nuxbe.io/packages.json' => Http::response([
            'packages' => [
                'team-nifty-gmbh/flux-erp' => [
                    '1.2.0' => ['name' => 'team-nifty-gmbh/flux-erp', 'version' => '1.2.0'],
                ],
                'team-nifty-gmbh/flux-dev-tools' => [
                    '0.5.0' => ['name' => 'team-nifty-gmbh/flux-dev-tools', 'version' => '0.5.0'],
                ],
            ],
        ], 200),
    ]);

    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'is_active' => true,
        'language_id' => $this->defaultLanguage->getKey(),
    ]);
    $admin->assignRole('Super Admin');

    $otherUser = User::factory()->create([
        'is_active' => true,
        'language_id' => $this->defaultLanguage->getKey(),
    ]);

    Artisan::call('flux-license:check-package-updates');

    Notification::assertSentTo(
        $admin,
        PackageUpdatesAvailable::class,
        function (PackageUpdatesAvailable $notification): bool {
            $names = collect($notification->updates)->pluck('name')->all();

            return in_array('team-nifty-gmbh/flux-erp', $names, true)
                && count($notification->updates) === 1; // dev-tools has equal version, not newer
        }
    );

    Notification::assertNotSentTo($otherUser, PackageUpdatesAvailable::class);
});

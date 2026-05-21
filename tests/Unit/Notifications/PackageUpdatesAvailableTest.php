<?php

use FluxErp\Models\User;
use TeamNiftyGmbH\FluxLicense\Notifications\PackageUpdatesAvailable;

test('toast notification renders title, description and action url', function (): void {
    $user = User::factory()->create([
        'is_active' => true,
        'language_id' => $this->defaultLanguage->getKey(),
    ]);

    $notification = new PackageUpdatesAvailable([
        ['name' => 'team-nifty-gmbh/flux-erp', 'current' => '1.0.0', 'available' => '1.2.0'],
        ['name' => 'team-nifty-gmbh/flux-license', 'current' => '0.8.0', 'available' => '0.9.0'],
    ]);

    $toast = $notification->toToastNotification($user);
    $data = $toast->toArray();

    expect($data['title'])->toBe('2 package update(s) available')
        ->and($data['description'])->toContain('team-nifty-gmbh/flux-erp: 1.0.0 → 1.2.0')
        ->and($data['description'])->toContain('team-nifty-gmbh/flux-license: 0.8.0 → 0.9.0')
        ->and($data['accept']['url'])->toBe(route('settings', ['setting-entry' => 'settings.plugins']));
});

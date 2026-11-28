<?php

use FluxErp\Models\Tenant;
use FluxErp\Models\User;
use FluxErp\Settings\CoreSettings;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use TeamNiftyGmbH\FluxLicense\Jobs\ReportUserActivation;

// User activations get reported through the queue, that would add requests to the fakes here
beforeEach(function (): void {
    Queue::fake([ReportUserActivation::class]);
});

test('command sends update to flux server with active users count', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    User::factory()->count(3)->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);
    User::factory()->count(2)->create(['is_active' => false, 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://flux.team-nifty.com/api/flux-licenses/test-license-key-12345'
            && $request['active_users'] === 4
            && count($request['users']) === 4;
    });
});

test('command sends the host of the app url so the server can tell a copy from the instance', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    config(['app.url' => 'https://Flux.Example.com/']);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => $request['domain'] === 'flux.example.com');
});

test('command sends correct user emails', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $activeUser1 = User::factory()->create(['is_active' => true, 'email' => 'active1@example.com', 'language_id' => $this->defaultLanguage->getKey()]);
    $activeUser2 = User::factory()->create(['is_active' => true, 'email' => 'active2@example.com', 'language_id' => $this->defaultLanguage->getKey()]);
    User::factory()->create(['is_active' => false, 'email' => 'inactive@example.com', 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $emails = collect($request['users'])->pluck('email')->toArray();

        return in_array('active1@example.com', $emails)
            && in_array('active2@example.com', $emails)
            && ! in_array('inactive@example.com', $emails);
    });
});

test('command handles failed HTTP request gracefully', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['error' => 'Server error'], 500),
    ]);

    User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->expectsOutput('Failed to send update to flux.team-nifty.com: 500')
        ->assertSuccessful();
});

test('command sends correct data structure', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    User::factory()->count(2)->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && isset($request['active_users'])
            && isset($request['users'])
            && is_array($request['users']);
    });
});

test('command uses license key from settings', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $customLicenseKey = 'custom-key-xyz-789';
    app(CoreSettings::class)->license_key = $customLicenseKey;
    app(CoreSettings::class)->save();

    User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) use ($customLicenseKey) {
        return str_contains($request->url(), $customLicenseKey);
    });
});

test('command sends zero active users when none exist', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    User::factory()->count(3)->create(['is_active' => false, 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request['active_users'] === 1
            && count($request['users']) === 1;
    });
});

test('command outputs success message on successful request', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->expectsOutput('Successfully sent update to flux.team-nifty.com')
        ->assertSuccessful();
});

test('command sends the tenants of every user', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $gmbh = Tenant::factory()->create(['name' => 'Restaurant GmbH']);
    $gbr = Tenant::factory()->create(['name' => 'Lieken GbR']);
    $deleted = Tenant::factory()->create(['name' => 'Deleted']);

    User::factory()
        ->create(['is_active' => true, 'email' => 'both@example.com', 'language_id' => $this->defaultLanguage->getKey()])
        ->tenants()
        ->attach([$gmbh->getKey(), $gbr->getKey(), $deleted->getKey()]);
    $deleted->delete();
    User::factory()
        ->create(['is_active' => true, 'email' => 'none@example.com', 'language_id' => $this->defaultLanguage->getKey()]);

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) use ($gmbh, $gbr) {
        $users = collect($request['users'])->keyBy('email');

        return collect($users->get('both@example.com')['tenants'])->sortBy('id')->values()->all() === [
            ['id' => $gmbh->getKey(), 'name' => 'Restaurant GmbH'],
            ['id' => $gbr->getKey(), 'name' => 'Lieken GbR'],
        ]
            && $users->get('none@example.com')['tenants'] === [];
    });
});

test('command reports all tenants of a user although the acting user sees fewer', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $gmbh = Tenant::factory()->create(['name' => 'Restaurant GmbH']);
    $gbr = Tenant::factory()->create(['name' => 'Lieken GbR']);

    $actingUser = User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);
    $actingUser->tenants()->attach($gmbh->getKey());
    User::factory()
        ->create(['is_active' => true, 'email' => 'both@example.com', 'language_id' => $this->defaultLanguage->getKey()])
        ->tenants()
        ->attach([$gmbh->getKey(), $gbr->getKey()]);

    // the command runs inside the request that saved a user, scoped to that user's tenants
    $this->actingAs($actingUser);
    Context::forget('user_tenant_ids');

    $this->artisan('flux-license:send-update')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $tenants = collect($request['users'])->keyBy('email')->get('both@example.com')['tenants'];

        return collect($tenants)->pluck('name')->sort()->values()->all() === ['Lieken GbR', 'Restaurant GmbH'];
    });
});

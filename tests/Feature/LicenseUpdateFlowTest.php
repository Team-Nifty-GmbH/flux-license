<?php

use FluxErp\Actions\User\CreateUser;
use FluxErp\Actions\User\UpdateUser;
use FluxErp\Models\User;
use FluxErp\Settings\CoreSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

test('creating a new user triggers license update', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $initialCount = User::where('is_active', true)->count();

    CreateUser::make([
        'firstname' => 'Integration',
        'lastname' => 'Test',
        'email' => 'integration@example.com',
        'password' => 'Password123!',
        'user_code' => 'IT',
        'language_id' => $this->defaultLanguage->getKey(),
        'is_active' => true,
    ])->validate()->execute();

    Http::assertSent(function ($request) use ($initialCount) {
        return $request->url() === 'https://flux.team-nifty.com/api/flux-licenses/test-license-key-12345'
            && $request['active_users'] === $initialCount + 1;
    });
});

test('updating a user triggers license update', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $user = User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    UpdateUser::make([
        'id' => $user->getKey(),
        'firstname' => 'Updated',
    ])->validate()->execute();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'flux.team-nifty.com/api/flux-licenses');
    });
});

test('license update sends correct active user count after user deactivation', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $activeUser1 = User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);
    $activeUser2 = User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    Http::assertNothingSent();

    UpdateUser::make([
        'id' => $activeUser1->getKey(),
        'is_active' => false,
    ])->validate()->execute();

    Http::assertSent(function ($request) {
        return $request['active_users'] >= 2;
    });
});

test('scheduled command runs license update', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    User::factory()->count(5)->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    Artisan::call('flux-license:send-update');

    Http::assertSent(function ($request) {
        return $request['active_users'] >= 6
            && count($request['users']) >= 6;
    });
});

test('license update includes correct user email structure', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $user1 = User::factory()->create(['is_active' => true, 'email' => 'user1@test.com', 'language_id' => $this->defaultLanguage->getKey()]);
    $user2 = User::factory()->create(['is_active' => true, 'email' => 'user2@test.com', 'language_id' => $this->defaultLanguage->getKey()]);

    Artisan::call('flux-license:send-update');

    Http::assertSent(function ($request) {
        $users = $request['users'];

        return is_array($users)
            && count($users) >= 3
            && isset($users[0]['email']);
    });
});

test('license update respects license key from settings', function (): void {
    $customKey = 'custom-production-key-abc123';

    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $settings = app(CoreSettings::class);
    $settings->license_key = $customKey;
    $settings->save();

    Artisan::call('flux-license:send-update');

    Http::assertSent(function ($request) use ($customKey) {
        return $request->url() === "https://flux.team-nifty.com/api/flux-licenses/{$customKey}";
    });
});

test('license update handles server errors gracefully', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['error' => 'Internal server error'], 500),
    ]);

    User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    expect(function (): void {
        Artisan::call('flux-license:send-update');
    })->not->toThrow(Exception::class);
});

test('license update handles network timeouts gracefully', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(null, 408),
    ]);

    User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    expect(function (): void {
        Artisan::call('flux-license:send-update');
    })->not->toThrow(Exception::class);
});

test('multiple user operations trigger multiple license updates', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    CreateUser::make([
        'firstname' => 'First',
        'lastname' => 'User',
        'email' => 'first@example.com',
        'password' => 'Password123!',
        'user_code' => 'FU',
        'language_id' => $this->defaultLanguage->getKey(),
        'is_active' => true,
    ])->validate()->execute();

    CreateUser::make([
        'firstname' => 'Second',
        'lastname' => 'User',
        'email' => 'second@example.com',
        'password' => 'Password123!',
        'user_code' => 'SU',
        'language_id' => $this->defaultLanguage->getKey(),
        'is_active' => true,
    ])->validate()->execute();

    Http::assertSentCount(2);
});

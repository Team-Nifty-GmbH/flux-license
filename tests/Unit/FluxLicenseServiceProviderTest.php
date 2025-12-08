<?php

use FluxErp\Actions\User\CreateUser;
use FluxErp\Actions\User\UpdateUser;
use FluxErp\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('service provider registers commands', function (): void {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('flux-license:send-update');
    expect($commands)->toHaveKey('flux:install');
});

test('service provider schedules daily license update', function (): void {
    $schedule = app()->make(Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());

    $hasScheduledCommand = $events->contains(function ($event) {
        return str_contains($event->command ?? '', 'flux-license:send-update');
    });

    expect($hasScheduledCommand)->toBeTrue();
});

test('service provider listens to user create action', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    Event::fake();

    $user = CreateUser::make([
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'user_code' => 'TU',
        'language_id' => $this->defaultLanguage->getKey(),
        'is_active' => true,
    ])->validate()->execute();

    expect($user)->toBeInstanceOf(User::class);

    Event::assertDispatched('action.executed: ' . resolve_static(CreateUser::class, 'class'));
});

test('service provider listens to user update action', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    Event::fake();

    $user = User::factory()->create(['language_id' => $this->defaultLanguage->getKey()]);

    UpdateUser::make([
        'id' => $user->getKey(),
        'firstname' => 'Updated',
    ])->validate()->execute();

    Event::assertDispatched('action.executed: ' . resolve_static(UpdateUser::class, 'class'));
});

test('service provider triggers license update on user creation', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    CreateUser::make([
        'firstname' => 'New',
        'lastname' => 'User',
        'email' => 'new@example.com',
        'password' => 'Password123!',
        'user_code' => 'NU',
        'language_id' => $this->defaultLanguage->getKey(),
        'is_active' => true,
    ])->validate()->execute();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'flux.team-nifty.com/api/flux-licenses');
    });
});

test('service provider triggers license update on user update', function (): void {
    Http::fake([
        'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
    ]);

    $user = User::factory()->create(['language_id' => $this->defaultLanguage->getKey()]);

    UpdateUser::make([
        'id' => $user->getKey(),
        'firstname' => 'Updated Name',
    ])->validate()->execute();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'flux.team-nifty.com/api/flux-licenses');
    });
});

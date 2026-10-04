<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Sleep::fake();
    config(['queue.default' => 'database']);
});

afterEach(function (): void {
    if (app()->isDownForMaintenance()) {
        Artisan::call('up');
    }
});

test('begin takes the app down, pauses all queues and interrupts the schedule', function (): void {
    $this->artisan('flux-license:maintenance-begin', ['--retry' => 120])
        ->assertSuccessful();

    expect(app()->isDownForMaintenance())->toBeTrue()
        ->and(Queue::isPaused('database', 'default'))->toBeTrue()
        ->and(Cache::get('illuminate:schedule:interrupt'))->toBeTrue();
});

test('begin waits until running jobs are finished', function (): void {
    $id = DB::table('jobs')->insertGetId([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 1,
        'reserved_at' => now()->timestamp,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    Sleep::whenFakingSleep(function () use ($id): void {
        DB::table('jobs')->where('id', $id)->delete();
    });

    $this->artisan('flux-license:maintenance-begin')
        ->assertSuccessful();

    Sleep::assertSleptTimes(1);
});

test('begin fails after the timeout and stays in maintenance', function (): void {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 1,
        'reserved_at' => now()->timestamp,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    $this->artisan('flux-license:maintenance-begin', ['--timeout' => 10])
        ->assertFailed();

    expect(app()->isDownForMaintenance())->toBeTrue()
        ->and(Queue::isPaused('database', 'default'))->toBeTrue();
});

test('begin is idempotent', function (): void {
    $this->artisan('flux-license:maintenance-begin')->assertSuccessful();
    $this->artisan('flux-license:maintenance-begin')->assertSuccessful();

    expect(app()->isDownForMaintenance())->toBeTrue();
});

test('end resumes the queues and brings the app up', function (): void {
    $this->artisan('flux-license:maintenance-begin')->assertSuccessful();

    $this->artisan('flux-license:maintenance-end')
        ->assertSuccessful();

    expect(app()->isDownForMaintenance())->toBeFalse()
        ->and(Queue::isPaused('database', 'default'))->toBeFalse();
});

test('end succeeds when the app was never in maintenance', function (): void {
    $this->artisan('flux-license:maintenance-end')
        ->assertSuccessful();

    expect(app()->isDownForMaintenance())->toBeFalse();
});

test('end fails when the health check does not answer successfully', function (): void {
    Http::fake(['example.test/*' => Http::response('', 500)]);

    $this->artisan('flux-license:maintenance-end', ['--health-url' => 'https://example.test/up'])
        ->assertFailed();

    expect(app()->isDownForMaintenance())->toBeFalse();
});

test('end passes the health check', function (): void {
    Http::fake(['example.test/*' => Http::response('ok')]);

    $this->artisan('flux-license:maintenance-end', ['--health-url' => 'https://example.test/up'])
        ->assertSuccessful();
});

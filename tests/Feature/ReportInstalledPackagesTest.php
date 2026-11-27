<?php

use FluxErp\Settings\CoreSettings;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->originalLock = File::exists(base_path('composer.lock'))
        ? File::get(base_path('composer.lock'))
        : null;

    File::put(base_path('composer.lock'), json_encode([
        'packages' => [
            ['name' => 'team-nifty-gmbh/flux-erp', 'version' => '1.0.0'],
            ['name' => 'symfony/console', 'version' => '7.0.0'],
        ],
    ]));

    app()->detectEnvironment(fn (): string => 'production');
});

afterEach(function (): void {
    if ($this->originalLock !== null) {
        File::put(base_path('composer.lock'), $this->originalLock);
    } else {
        File::delete(base_path('composer.lock'));
    }
});

function migrationsEnded(string $method = 'up', array $options = []): void
{
    event(new MigrationsEnded($method, $options));
}

function packagesCacheKey(string $licenseKey = 'test-license-key-12345'): string
{
    return 'flux-license.packages-hash.' . md5($licenseKey);
}

test('migrations ended reports changed packages', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 200)]);

    migrationsEnded();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://flux.team-nifty.com/api/flux-licenses/test-license-key-12345/packages'
        && $request['packages'] === ['team-nifty-gmbh/flux-erp' => ['version' => '1.0.0']]
    );
    expect(Cache::get(packagesCacheKey()))->toBe(md5_file(base_path('composer.lock')));
});

test('unchanged lock reports nothing', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 200)]);

    migrationsEnded();
    migrationsEnded();

    Http::assertSentCount(1);

    File::put(base_path('composer.lock'), json_encode([
        'packages' => [['name' => 'team-nifty-gmbh/flux-erp', 'version' => '1.0.1']],
    ]));

    migrationsEnded();

    Http::assertSentCount(2);
});

test('failed report does not throw and retries next deploy', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::sequence()
        ->push(status: 500)
        ->pushFailedConnection()
        ->push(status: 200),
    ]);

    migrationsEnded();
    expect(Cache::get(packagesCacheKey()))->toBeNull();

    migrationsEnded();
    expect(Cache::get(packagesCacheKey()))->toBeNull();

    migrationsEnded();
    expect(Cache::get(packagesCacheKey()))->not->toBeNull();

    Http::assertSentCount(3);
});

test('no license key sends nothing', function (): void {
    Http::fake();
    app(CoreSettings::class)->license_key = null;

    migrationsEnded();

    Http::assertNothingSent();
});

test('no pending migrations reports too', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 200)]);

    event(new NoPendingMigrations('up'));

    Http::assertSentCount(1);
});

test('down runs and pretend runs report nothing', function (): void {
    Http::fake();

    migrationsEnded('down');
    migrationsEnded('up', ['pretend' => true]);

    Http::assertNothingSent();
});

test('outside production nothing is reported', function (): void {
    Http::fake();
    app()->detectEnvironment(fn (): string => 'local');

    migrationsEnded();

    Http::assertNothingSent();
});

test('a failing settings read does not throw', function (): void {
    Http::fake();
    $this->mock(CoreSettings::class)->shouldReceive('__get')->andThrow(new RuntimeException('no settings table'));

    migrationsEnded();

    Http::assertNothingSent();
});

test('a missing composer.lock sends nothing', function (): void {
    Http::fake();
    File::delete(base_path('composer.lock'));

    migrationsEnded();

    Http::assertNothingSent();
});

test('a failed response is logged with its status', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 503)]);
    Log::spy();

    migrationsEnded();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context === ['status' => 503])->once();
});

test('a changed license key reports again', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 200)]);

    migrationsEnded();
    app(CoreSettings::class)->license_key = 'another-key';
    migrationsEnded();

    Http::assertSentCount(2);
    expect(Cache::get(packagesCacheKey('another-key')))->not->toBeNull();
});

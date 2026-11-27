<?php

use FluxErp\Settings\CoreSettings;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

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

    Cache::forget('flux-license.packages-hash');
});

afterEach(function (): void {
    if ($this->originalLock !== null) {
        File::put(base_path('composer.lock'), $this->originalLock);
    } else {
        File::delete(base_path('composer.lock'));
    }
});

function migrationsEnded(): void
{
    event(new MigrationsEnded('up'));
}

test('migrations ended reports changed packages', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 200)]);

    migrationsEnded();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://flux.team-nifty.com/api/flux-licenses/test-license-key-12345/packages'
        && $request['packages'] === ['team-nifty-gmbh/flux-erp' => ['version' => '1.0.0']]
    );
    expect(Cache::get('flux-license.packages-hash'))->toBe(md5_file(base_path('composer.lock')));
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
    expect(Cache::get('flux-license.packages-hash'))->toBeNull();

    migrationsEnded();
    expect(Cache::get('flux-license.packages-hash'))->toBeNull();

    migrationsEnded();
    expect(Cache::get('flux-license.packages-hash'))->not->toBeNull();

    Http::assertSentCount(3);
});

test('no license key sends nothing', function (): void {
    Http::fake();
    app(CoreSettings::class)->license_key = null;

    migrationsEnded();

    Http::assertNothingSent();
});

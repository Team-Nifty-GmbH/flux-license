<?php

describe('System Status Endpoint', function (): void {
    it('returns 401 without authorization', function (): void {
        $this->getJson('/api/flux-license/system-status')
            ->assertUnauthorized();
    });

    it('returns 401 with invalid license key', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer invalid-key',
        ])->assertUnauthorized();
    });

    it('returns 200 with valid license key', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ])->assertOk();
    });

    it('returns laravel information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'laravel' => [
                    'version',
                    'environment',
                    'debug_mode',
                    'timezone',
                    'locale',
                ],
            ]);

        expect($response->json('laravel.version'))->toBe(app()->version());
    });

    it('returns php information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'php' => [
                    'version',
                    'memory_limit',
                    'max_execution_time',
                    'upload_max_filesize',
                    'post_max_size',
                    'extensions',
                ],
            ]);

        expect($response->json('php.version'))->toBe(phpversion())
            ->and($response->json('php.extensions'))->toBeArray()->not->toBeEmpty();
    });

    it('returns server information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'server' => [
                    'os',
                    'software',
                    'document_root',
                    'server_name',
                    'runtime',
                    'octane',
                    'octane_server',
                ],
            ]);

        expect($response->json('server.runtime'))->toBeString()
            ->and($response->json('server.octane'))->toBeBool();
    });

    it('returns database information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'database' => [
                    'connection',
                    'driver',
                    'host',
                    'port',
                ],
            ]);
    });

    it('returns cache information', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ])->assertOk()
            ->assertJsonStructure([
                'cache' => ['driver', 'prefix'],
            ]);
    });

    it('returns queue information', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ])->assertOk()
            ->assertJsonStructure([
                'queue' => ['connection', 'driver', 'queue', 'size'],
            ]);
    });

    it('returns storage information', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ])->assertOk()
            ->assertJsonStructure([
                'storage' => ['disk_free_space', 'disk_total_space', 'view_cache_space'],
            ]);
    });

    it('returns session information', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ])->assertOk()
            ->assertJsonStructure([
                'session' => ['driver', 'lifetime', 'secure', 'same_site'],
            ]);
    });

    it('returns broadcasting information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'broadcasting' => [
                    'driver',
                    'reverb' => [
                        'server' => ['host', 'port', 'scheme', 'app_key'],
                        'frontend' => ['host', 'port', 'scheme', 'app_key'],
                        'config_match',
                        'issues',
                    ],
                ],
            ]);

        expect($response->json('broadcasting.reverb.config_match'))->toBeBool()
            ->and($response->json('broadcasting.reverb.issues'))->toBeArray();
    });

    it('returns scout information', function (): void {
        $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ])->assertOk()
            ->assertJsonStructure([
                'scout' => ['driver'],
            ]);
    });

    it('returns installed packages', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['packages']);

        expect($response->json('packages'))->toBeArray();
    });

    it('parses composer.lock when available', function (): void {
        $fakeLock = [
            'packages' => [
                ['name' => 'team-nifty-gmbh/flux-erp', 'version' => 'v1.0.2'],
                ['name' => 'laravel/framework', 'version' => 'v13.3.0'],
                ['name' => 'some/other-package', 'version' => '1.0.0'],
            ],
        ];

        $lockPath = base_path('composer.lock');
        file_put_contents($lockPath, json_encode($fakeLock));

        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $packages = $response->json('packages');
        expect($packages)->toHaveKey('team-nifty-gmbh/flux-erp')
            ->and($packages['team-nifty-gmbh/flux-erp']['version'])->toBe('v1.0.2')
            ->and($packages)->toHaveKey('laravel/framework')
            ->and($packages)->not->toHaveKey('some/other-package');

        @unlink($lockPath);
    });

    it('returns active users count', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk();
        expect($response->json('active_users'))->toBeInt();
    });

    it('is rate limited', function (): void {
        for ($i = 0; $i < 12; $i++) {
            $response = $this->getJson('/api/flux-license/system-status', [
                'Authorization' => 'Bearer test-license-key-12345',
            ]);
        }

        $response->assertStatus(429);
    });
});

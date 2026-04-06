<?php

describe('System Status Endpoint', function (): void {
    it('returns 401 without authorization', function (): void {
        $response = $this->getJson('/api/flux-license/system-status');

        $response->assertUnauthorized();
    });

    it('returns 401 with invalid license key', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer invalid-key',
        ]);

        $response->assertUnauthorized();
    });

    it('returns 200 with valid license key', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk();
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
                ],
            ]);

        expect($response->json('php.version'))->toBe(phpversion());
    });

    it('returns server information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'server' => [
                    'os',
                    'document_root',
                ],
            ]);
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
                ],
            ]);
    });

    it('returns cache information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'cache' => [
                    'driver',
                    'prefix',
                ],
            ]);
    });

    it('returns queue information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'queue' => [
                    'connection',
                    'driver',
                    'queue',
                    'size',
                ],
            ]);
    });

    it('returns storage information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'storage' => [
                    'disk_free_space',
                    'disk_total_space',
                ],
            ]);
    });

    it('returns session information', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'session' => [
                    'driver',
                    'lifetime',
                ],
            ]);
    });

    it('returns active users count', function (): void {
        $response = $this->getJson('/api/flux-license/system-status', [
            'Authorization' => 'Bearer test-license-key-12345',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'active_users',
            ]);

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

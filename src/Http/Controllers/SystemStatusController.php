<?php

namespace TeamNiftyGmbH\FluxLicense\Http\Controllers;

use FluxErp\Models\User;
use FluxErp\Settings\CoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Number;

class SystemStatusController
{
    public function __invoke(Request $request): JsonResponse
    {
        $licenseKey = app(CoreSettings::class)->license_key;

        if (blank($licenseKey) || $request->bearerToken() !== $licenseKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'laravel' => [
                'version' => app()->version(),
                'environment' => app()->environment(),
                'debug_mode' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'php' => [
                'version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'extensions' => collect(get_loaded_extensions())->sort()->values()->toArray(),
            ],
            'server' => [
                'os' => php_uname(),
                'software' => data_get($_SERVER, 'SERVER_SOFTWARE'),
                'document_root' => data_get($_SERVER, 'DOCUMENT_ROOT'),
                'server_name' => data_get($_SERVER, 'SERVER_NAME'),
                'runtime' => php_sapi_name(),
                'octane' => app()->bound('Laravel\Octane\Octane') || class_exists('Laravel\Octane\Octane') && config('octane.server'),
                'octane_server' => config('octane.server'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
                'port' => config('database.connections.' . config('database.default') . '.port'),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'prefix' => config('cache.prefix'),
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'driver' => config('queue.connections.' . config('queue.default') . '.driver'),
                'queue' => config('queue.connections.' . config('queue.default') . '.queue'),
                'size' => Queue::size(),
            ],
            'storage' => [
                'disk_free_space' => Number::fileSize(disk_free_space('/'), 2),
                'disk_total_space' => Number::fileSize(disk_total_space('/'), 2),
                'view_cache_space' => Number::fileSize(
                    array_reduce(
                        glob(storage_path('framework/views/*')),
                        fn ($carry, $item) => $carry + (is_dir($item) ? 0 : filesize($item)),
                        0
                    ),
                    2
                ),
            ],
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
                'secure' => config('session.secure'),
                'same_site' => config('session.same_site'),
            ],
            'broadcasting' => $this->getBroadcastingInfo(),
            'scout' => [
                'driver' => config('scout.driver'),
            ],
            'packages' => $this->getInstalledPackages(),
            'active_users' => User::query()->where('is_active', true)->count(),
        ]);
    }

    protected function getInstalledPackages(): array
    {
        $lockFile = base_path('composer.lock');

        if (! file_exists($lockFile)) {
            return [];
        }

        $lock = json_decode(file_get_contents($lockFile), true);

        return collect(data_get($lock, 'packages', []))
            ->filter(fn (array $package) => str_starts_with($package['name'], 'team-nifty-gmbh/')
                || str_starts_with($package['name'], 'laravel/')
                || str_starts_with($package['name'], 'livewire/')
                || str_starts_with($package['name'], 'tallstackui/')
                || str_starts_with($package['name'], 'saloonphp/')
                || str_starts_with($package['name'], 'spatie/')
            )
            ->mapWithKeys(fn (array $package) => [
                $package['name'] => [
                    'version' => $package['version'],
                ],
            ])
            ->sortKeys()
            ->toArray();
    }

    protected function getBroadcastingInfo(): array
    {
        $serverKey = config('reverb.apps.apps.0.key');
        $serverHost = config('reverb.servers.reverb.host', '0.0.0.0');
        $serverPort = config('reverb.servers.reverb.port', 8080);
        $serverScheme = config('reverb.servers.reverb.hostname') ? 'https' : 'http';

        $frontendKey = config('flux.vite.reverb_app_key');
        $frontendHost = config('flux.vite.reverb_host');
        $frontendPort = config('flux.vite.reverb_port');
        $frontendScheme = config('flux.vite.reverb_protocol');

        $issues = [];

        if ($serverKey !== $frontendKey) {
            $issues[] = 'App key mismatch between server and frontend';
        }

        if (blank($frontendHost) || in_array($frontendHost, ['localhost', '127.0.0.1'])) {
            $issues[] = 'Frontend host is localhost or empty - clients cannot connect';
        }

        if (blank($frontendPort) || str_contains((string) $frontendPort, '${')) {
            $issues[] = 'Frontend port contains unresolved env variable';
        }

        if (blank($frontendScheme) || str_contains((string) $frontendScheme, '${')) {
            $issues[] = 'Frontend scheme contains unresolved env variable';
        }

        return [
            'driver' => config('broadcasting.default'),
            'reverb' => [
                'server' => [
                    'host' => $serverHost,
                    'port' => $serverPort,
                    'scheme' => $serverScheme,
                    'app_key' => $serverKey,
                ],
                'frontend' => [
                    'host' => $frontendHost,
                    'port' => $frontendPort,
                    'scheme' => $frontendScheme,
                    'app_key' => $frontendKey,
                ],
                'config_match' => empty($issues),
                'issues' => $issues,
            ],
        ];
    }
}

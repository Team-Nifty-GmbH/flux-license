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
            'php' => [
                'version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'server' => [
                'os' => php_uname(),
                'document_root' => data_get($_SERVER, 'DOCUMENT_ROOT'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'driver' => config('database.connections.' . config('database.default') . '.driver'),
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
            ],
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
            ],
            'active_users' => User::query()->where('is_active', true)->count(),
        ]);
    }
}

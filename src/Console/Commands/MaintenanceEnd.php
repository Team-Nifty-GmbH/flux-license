<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Throwable;

class MaintenanceEnd extends Command
{
    protected $description = 'Bring the instance back after flux-license:maintenance-begin and optionally check that it answers';

    protected $signature = 'flux-license:maintenance-end
        {--health-url= : URL that has to answer with a 2xx status afterwards, e.g. the /up route}';

    public function handle(): int
    {
        Queue::resumeAll();

        if (array_key_exists('horizon:continue', Artisan::all())) {
            $this->call('horizon:continue');
        }

        if ($this->laravel->isDownForMaintenance()) {
            $this->call('up');
        }

        if (! $url = $this->option('health-url')) {
            return self::SUCCESS;
        }

        try {
            $status = Http::timeout(15)->get($url)->status();
        } catch (Throwable $e) {
            $status = $e->getMessage();
        }

        if (! is_int($status) || $status >= 300) {
            $this->error(__('Health check :url failed: :status', ['url' => $url, 'status' => $status]));

            return self::FAILURE;
        }

        $this->info(__('Instance is back and :url answers', ['url' => $url]));

        return self::SUCCESS;
    }
}

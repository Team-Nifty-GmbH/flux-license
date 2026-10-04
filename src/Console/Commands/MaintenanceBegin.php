<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

class MaintenanceBegin extends Command
{
    protected $description = 'Take the instance offline cleanly: maintenance mode, paused queues, interrupted schedule, no running jobs';

    protected $signature = 'flux-license:maintenance-begin
        {--retry=60 : Retry-After header in seconds sent with the 503 response}
        {--secret= : Secret that lets a request bypass maintenance mode}
        {--timeout=300 : Seconds to wait for running jobs to finish}';

    public function handle(): int
    {
        if (! $this->laravel->isDownForMaintenance()) {
            $this->call('down', array_filter([
                '--retry' => $this->option('retry'),
                '--secret' => $this->option('secret'),
            ]));
        }

        // Workers stop taking new jobs, the ones already running are allowed to finish.
        Queue::pauseAll();

        if (array_key_exists('horizon:pause', Artisan::all())) {
            $this->call('horizon:pause');
        }

        $this->call('schedule:interrupt');

        $timeout = (int) $this->option('timeout');

        for ($waited = 0; ($running = $this->runningJobs()) > 0; $waited += 2) {
            if ($waited >= $timeout) {
                // Stay down and paused: half way back up is worse than waiting for a human.
                $this->error(__(':count jobs still running after :seconds seconds, run flux-license:maintenance-end to bring the instance back', [
                    'count' => $running,
                    'seconds' => $timeout,
                ]));

                return self::FAILURE;
            }

            Sleep::for(2)->seconds();
        }

        $this->info(__('Instance is in maintenance, no jobs running'));

        return self::SUCCESS;
    }

    protected function runningJobs(): int
    {
        $queue = Queue::connection();

        // ponytail: only the default connection is checked, add others when an instance runs workers on a second one
        return method_exists($queue, 'totalReservedSize') ? (int) $queue->totalReservedSize() : 0;
    }
}

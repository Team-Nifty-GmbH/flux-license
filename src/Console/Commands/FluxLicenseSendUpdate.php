<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use FluxErp\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FluxLicenseSendUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flux-license:send-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send currently active users to flux.team-nifty.com';

    public function handle(): void
    {
        $response = Http::post(
            'https://flux.team-nifty.com/api/flux-licenses/' . config('flux.license_key'),
            [
                'active_users' => User::query()->where('is_active', true)->count(),
                'users' => User::query()->where('is_active', true)->get(['email'])->toArray(),
            ]
        );

        if ($response->failed()) {
            $this->error('Failed to send update to flux.team-nifty.com: ' . $response->getStatusCode());

            return;
        }

        $this->info('Successfully sent update to flux.team-nifty.com');
    }
}

<?php

namespace TeamNiftyGmbH\FluxLicense\Jobs;

use FluxErp\Settings\CoreSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

// Queued with generous retries: the report is the proof of who accepted the costs
class ReportUserActivation implements ShouldQueue
{
    use Queueable;

    public array $backoff = [60, 300, 900, 3600];

    public int $tries = 20;

    public function __construct(public array $data) {}

    public function handle(): void
    {
        Http::timeout(10)
            ->acceptJson()
            ->post(
                'https://flux.team-nifty.com/api/flux-licenses/' . app(CoreSettings::class)->license_key . '/user-activations',
                $this->data
            )
            ->throw();
    }
}

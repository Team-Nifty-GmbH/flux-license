<?php

namespace TeamNiftyGmbH\FluxLicense\Jobs;

use FluxErp\Models\User;
use FluxErp\Settings\CoreSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TeamNiftyGmbH\FluxLicense\Livewire\ConfirmBillableUserActivation;
use TeamNiftyGmbH\FluxLicense\Support\LicensePricing;
use Throwable;

// Queued with generous retries: the report is the proof of who activated a billable user
class ReportUserActivation implements ShouldQueue
{
    use Queueable;

    public array $backoff = [60, 300, 900, 3600];

    public int $tries = 20;

    public function __construct(public array $data, public int $activeUsersBefore) {}

    // Called for every user that just became active, whichever way it happened
    public static function forUser(User $user): void
    {
        $actor = auth()->user();
        $token = method_exists($actor ?? '', 'currentAccessToken') ? $actor->currentAccessToken() : null;

        $data = [
            'source' => match (true) {
                (bool) request()->attributes->get(ConfirmBillableUserActivation::CONFIRMED) => 'ui',
                request()->is('api/*') => 'api',
                app()->runningInConsole() => 'console',
                default => 'other',
            },
            'activated_user_email' => $user->email,
            'activated_user_name' => $user->name,
            'confirmed_by_email' => $actor?->email,
            'confirmed_by_name' => $actor?->name,
            'token_name' => data_get($token, 'name'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'confirmed_at' => now()->toIso8601String(),
        ];

        // A failing report must never stop the activation itself, e.g. on a sync queue
        try {
            static::dispatch($data, User::query()->where('is_active', true)->count() - 1)->afterCommit();
        } catch (Throwable $e) {
            Log::error('flux-license: failed to report user activation', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function handle(): void
    {
        $pricing = LicensePricing::get();

        // An unknown price is reported anyway, only a known free activation is not
        if (! is_null(data_get($pricing, 'unit_price'))
            && ! LicensePricing::addsToBill($pricing, $this->activeUsersBefore)
        ) {
            return;
        }

        Http::timeout(10)
            ->acceptJson()
            ->post(
                'https://flux.team-nifty.com/api/flux-licenses/' . app(CoreSettings::class)->license_key . '/user-activations',
                array_merge($this->data, [
                    'unit_price' => data_get($pricing, 'unit_price'),
                    'currency' => data_get($pricing, 'currency'),
                ])
            )
            ->throw();
    }
}

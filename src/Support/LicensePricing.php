<?php

namespace TeamNiftyGmbH\FluxLicense\Support;

use FluxErp\Settings\CoreSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class LicensePricing
{
    public static function get(): ?array
    {
        try {
            return Cache::remember('flux-license:pricing', now()->addDay(), fn (): array => Http::timeout(5)
                ->acceptJson()
                ->get('https://flux.team-nifty.com/api/flux-licenses/' . app(CoreSettings::class)->license_key . '/pricing')
                ->throw()
                ->json()
            );
        } catch (Throwable) {
            return null;
        }
    }

    // Mirrors the billed amount FluxLicense::updateOrder() computes on the license server
    public static function addsToBill(array $pricing, int $activeUsersBefore): bool
    {
        $billed = fn (int $users): int => min(
            max(max($users, $pricing['min_accounts']) - $pricing['free_accounts'], 0),
            $pricing['max_accounts']
        );

        return $billed($activeUsersBefore + 1) > $billed($activeUsersBefore);
    }
}

<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use FluxErp\Models\User;
use FluxErp\Settings\CoreSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FluxLicenseSendUpdate extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send currently active users to flux.team-nifty.com';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flux-license:send-update';

    public function handle(): void
    {
        // read the pivot directly: the tenant model is scoped to the tenants of a logged in user,
        // and this command also runs inside the request that saved a user
        $tenants = DB::table('tenant_user')
            ->join('tenants', 'tenants.id', '=', 'tenant_user.tenant_id')
            ->whereNull('tenants.deleted_at')
            ->get(['tenant_user.user_id', 'tenants.id', 'tenants.name'])
            ->groupBy('user_id');

        $response = Http::post(
            'https://flux.team-nifty.com/api/flux-licenses/' . app(CoreSettings::class)->license_key,
            [
                'active_users' => User::query()->where('is_active', true)->count(),
                // tenants let the license server split the bill per tenant, older servers ignore the key
                'users' => User::query()
                    ->where('is_active', true)
                    ->get(['id', 'email'])
                    ->map(fn (User $user): array => [
                        'email' => $user->email,
                        'tenants' => $tenants->get($user->getKey(), collect())
                            ->map(fn (object $tenant): array => ['id' => $tenant->id, 'name' => $tenant->name])
                            ->values()
                            ->all(),
                    ])
                    ->all(),
                'domain' => strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            ]
        );

        if ($response->failed()) {
            $this->error('Failed to send update to flux.team-nifty.com: ' . $response->getStatusCode());

            return;
        }

        $this->info('Successfully sent update to flux.team-nifty.com');
    }
}

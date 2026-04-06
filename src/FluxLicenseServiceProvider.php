<?php

namespace TeamNiftyGmbH\FluxLicense;

use FluxErp\Actions\User\CreateUser;
use FluxErp\Actions\User\UpdateUser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use TeamNiftyGmbH\FluxLicense\Console\Commands\FluxLicenseSendUpdate;
use TeamNiftyGmbH\FluxLicense\Console\Commands\Install;
use TeamNiftyGmbH\FluxLicense\Http\Controllers\SystemStatusController;

class FluxLicenseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('throttle:10,1')
            ->get('api/flux-license/system-status', SystemStatusController::class);

        Event::listen(
            'action.executed: ' . resolve_static(UpdateUser::class, 'class'),
            function (): void {
                Artisan::call('flux-license:send-update');
            }
        );

        Event::listen(
            'action.executed: ' . resolve_static(CreateUser::class, 'class'),
            function (): void {
                Artisan::call('flux-license:send-update');
            }
        );
    }

    public function register(): void
    {
        $this->commands([
            FluxLicenseSendUpdate::class,
            Install::class,
        ]);

        $this->app->booted(function (): void {
            $scheduler = $this->app->make(Schedule::class);
            $scheduler->command(FluxLicenseSendUpdate::class)->daily();
        });
    }
}

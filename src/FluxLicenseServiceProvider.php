<?php

namespace TeamNiftyGmbH\FluxLicense;

use FluxErp\Actions\User\CreateUser;
use FluxErp\Actions\User\UpdateUser;
use FluxErp\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\ComponentHookRegistry;
use TeamNiftyGmbH\FluxLicense\Console\Commands\FluxLicenseCheckPackageUpdates;
use TeamNiftyGmbH\FluxLicense\Console\Commands\FluxLicenseSendUpdate;
use TeamNiftyGmbH\FluxLicense\Console\Commands\Install;
use TeamNiftyGmbH\FluxLicense\Console\Commands\MaintenanceBegin;
use TeamNiftyGmbH\FluxLicense\Console\Commands\MaintenanceEnd;
use TeamNiftyGmbH\FluxLicense\Http\Controllers\SystemStatusController;
use TeamNiftyGmbH\FluxLicense\Jobs\ReportUserActivation;
use TeamNiftyGmbH\FluxLicense\Livewire\ConfirmBillableUserActivation;

class FluxLicenseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('throttle:10,1')
            ->get('api/flux-license/system-status', SystemStatusController::class);

        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');

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

        // Model events instead of the actions, so no way of activating a user goes unreported
        Event::listen(
            'eloquent.created: ' . resolve_static(User::class, 'class'),
            function (User $user): void {
                if ($user->is_active) {
                    ReportUserActivation::forUser($user);
                }
            }
        );

        Event::listen(
            'eloquent.updated: ' . resolve_static(User::class, 'class'),
            function (User $user): void {
                if ($user->is_active && $user->wasChanged('is_active')) {
                    ReportUserActivation::forUser($user);
                }
            }
        );
    }

    public function register(): void
    {
        $this->commands([
            FluxLicenseCheckPackageUpdates::class,
            FluxLicenseSendUpdate::class,
            Install::class,
            MaintenanceBegin::class,
            MaintenanceEnd::class,
        ]);

        // Livewire boots its hooks before this provider's boot() runs
        ComponentHookRegistry::register(ConfirmBillableUserActivation::class);

        $this->app->booted(function (): void {
            $scheduler = $this->app->make(Schedule::class);
            $scheduler->command(FluxLicenseSendUpdate::class)->daily();
            $scheduler->command(FluxLicenseCheckPackageUpdates::class)->dailyAt('03:00');
        });
    }
}

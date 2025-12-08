<?php

namespace TeamNiftyGmbH\FluxLicense\Tests;

use Barryvdh\DomPDF\ServiceProvider;
use FluxErp\FluxServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Scout\ScoutServiceProvider;
use Livewire\LivewireServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use NotificationChannels\WebPush\WebPushServiceProvider;
use Orchestra\Testbench\Concerns\CreatesApplication;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelSettings\LaravelSettingsServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;
use Spatie\Tags\TagsServiceProvider;
use Spatie\Translatable\TranslatableServiceProvider;
use Spatie\TranslationLoader\TranslationServiceProvider;
use TallStackUi\Facades\TallStackUi;
use TallStackUi\TallStackUiServiceProvider;
use TeamNiftyGmbH\DataTable\DataTableServiceProvider;
use TeamNiftyGmbH\FluxLicense\FluxLicenseServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected $loadEnvironmentVariables = true;

    public function getPackageProviders($app): array
    {
        return [
            LaravelSettingsServiceProvider::class,
            TranslationServiceProvider::class,
            TranslatableServiceProvider::class,
            LivewireServiceProvider::class,
            TallStackUiServiceProvider::class,
            PermissionServiceProvider::class,
            TagsServiceProvider::class,
            ScoutServiceProvider::class,
            MediaLibraryServiceProvider::class,
            QueryBuilderServiceProvider::class,
            DataTableServiceProvider::class,
            ActivitylogServiceProvider::class,
            FluxServiceProvider::class,
            WebPushServiceProvider::class,
            ServiceProvider::class,
            ExcelServiceProvider::class,
            FluxLicenseServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        if (! is_dir(database_path('settings'))) {
            mkdir(database_path('settings'));
        }

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('flux.install_done', true);
        $app['config']->set('auth.defaults.guard', 'sanctum');
        $app['config']->set('cache.default', 'array');

        // Load views and translations
        $app['view']->addNamespace('flux-license', __DIR__ . '/../resources/views');
        $app['translator']->addJsonPath(__DIR__ . '/../lang');
    }

    protected function getPackageAliases($app): array
    {
        return ['TallStackUi' => TallStackUi::class];
    }
}

<?php

namespace TeamNiftyGmbH\FluxLicense\Console\Commands;

use Exception;
use FluxErp\Actions\Currency\CreateCurrency;
use FluxErp\Actions\Language\CreateLanguage;
use FluxErp\Actions\OrderType\CreateOrderType;
use FluxErp\Actions\PaymentType\CreatePaymentType;
use FluxErp\Actions\PriceList\CreatePriceList;
use FluxErp\Actions\Tenant\CreateTenant;
use FluxErp\Actions\User\CreateUser;
use FluxErp\Actions\VatRate\CreateVatRate;
use FluxErp\Actions\Warehouse\CreateWarehouse;
use FluxErp\Enums\OrderTypeEnum;
use FluxErp\Models\Currency;
use FluxErp\Models\Language;
use FluxErp\Models\OrderType;
use FluxErp\Models\PaymentType;
use FluxErp\Models\PriceList;
use FluxErp\Models\Role;
use FluxErp\Models\Tenant;
use FluxErp\Models\User;
use FluxErp\Models\VatRate;
use FluxErp\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use NotificationChannels\WebPush\VapidKeysGenerateCommand;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class Install extends Command
{
    protected array $tenantData = [];

    protected array $currencyData = [];

    protected $description = 'Interactive Flux ERP installation wizard';

    protected array $languageData = [];

    protected array $orderTypes = [];

    protected array $paymentTypes = [];

    protected $signature = 'flux:install
                            {--language-code=en : Language code}
                            {--language-name=English : Language name}
                            {--currency-name=Euro : Currency name}
                            {--currency-iso=EUR : Currency ISO code}
                            {--currency-symbol=€ : Currency symbol}
                            {--company-name= : Company name}
                            {--company-code= : Company code}
                            {--company-email= : Company email}
                            {--company-phone= : Company phone}
                            {--company-street= : Company street}
                            {--company-postcode= : Company postcode}
                            {--company-city= : Company city}
                            {--vat-rates= : VAT rates (comma separated, format: name:percentage)}
                            {--payment-types= : Payment types (comma separated, format: name:description)}
                            {--payment-type-name=Cash : Default payment type name (if payment-types not provided)}
                            {--payment-type-description= : Default payment type description (if payment-types not provided)}
                            {--order-types=* : Order types to create (multiple values allowed)}
                            {--admin-firstname= : Admin first name}
                            {--admin-lastname= : Admin last name}
                            {--admin-email= : Admin email}
                            {--admin-password= : Admin password}';

    protected array $userData = [];

    protected array $vatRates = [];

    public function handle(): int
    {
        if (config('flux.install_done')) {
            $this->error(__('Installation already completed.'));

            return 1;
        }

        info(__('Welcome to the Flux ERP Installation'));

        $this->setupDatabase();

        if ($this->option('no-interaction')) {
            $this->setupDataFromOptions();
        } else {
            $this->setupLanguage();
            $this->setupCurrency();
            $this->setupTenant();
            $this->setupVatRates();
            $this->setupPaymentType();
            $this->setupOrderTypes();
            $this->setupUser();
        }

        $this->runInitialCommands();

        DB::beginTransaction();
        try {
            $this->finishInstallation();
            DB::commit();

            info(__('Installation completed successfully!'));
        } catch (Exception $e) {
            DB::rollBack();
            $this->error(__('Installation failed: ') . $e->getMessage());

            if ($e instanceof ValidationException) {
                foreach ($e->errors() as $field => $errors) {
                    foreach ($errors as $error) {
                        $this->error("- {$field}: {$error}");
                    }
                }
            }

            return 1;
        }

        $this->askForPhpFpmRestart();

        return 0;
    }

    protected function askForPhpFpmRestart(): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        if (confirm(__('Would you like to restart PHP-FPM?'), true)) {
            $this->info(__('Restarting PHP-FPM...'));

            $commands = [
                'sudo systemctl restart php-fpm',
                'sudo systemctl restart php8.4-fpm',
                'sudo systemctl restart php8.3-fpm',
                'sudo service php-fpm restart',
                'sudo service php8.4-fpm restart',
                'sudo service php8.3-fpm restart',
            ];

            $success = false;
            foreach ($commands as $command) {
                $exitCode = 0;
                exec($command . ' 2>/dev/null', $output, $exitCode);

                if ($exitCode === 0) {
                    info(__('PHP-FPM restarted successfully with: ') . $command);
                    $success = true;

                    break;
                }
            }

            if (! $success) {
                $this->warn(__('Could not automatically restart PHP-FPM. Please restart it manually.'));
                $this->line(__('Try one of these commands:'));

                foreach ($commands as $command) {
                    $this->line('  ' . $command);
                }
            }
        }
    }

    protected function finishInstallation(): void
    {
        info(__('Creating database entries...'));

        if (! isset($this->languageData['id'])) {
            $language = CreateLanguage::make($this->languageData)->validate()->execute();
        } else {
            $language = resolve_static(Language::class, 'query')
                ->whereKey($this->languageData['id'])
                ->first();
        }

        if (! isset($this->currencyData['id'])) {
            CreateCurrency::make($this->currencyData)
                ->validate()
                ->execute();
        }

        if (! isset($this->tenantData['id'])) {
            $tenant = CreateTenant::make($this->tenantData)
                ->validate()
                ->execute();
        } else {
            $tenant = resolve_static(Tenant::class, 'query')
                ->whereKey($this->tenantData['id'])
                ->first();
        }

        if (! isset($this->userData['id'])) {
            $this->userData['language_id'] = $language->id;
            $user = CreateUser::make($this->userData)
                ->validate()
                ->execute();

            $user->assignRole(
                resolve_static(Role::class, 'query')
                    ->where('name', 'Super Admin')
                    ->where('guard_name', 'web')
                    ->first()
            );
        }

        if (
            $this->languageData['language_code'] !== 'en'
            && resolve_static(Language::class, 'query')
                ->where('language_code', 'en')
                ->doesntExist()
        ) {
            CreateLanguage::make([
                'name' => 'English',
                'language_code' => 'en',
                'iso_name' => 'en',
            ])
                ->validate()
                ->execute();
        }

        foreach ($this->vatRates as $vatRate) {
            if (! isset($vatRate['id'])) {
                CreateVatRate::make($vatRate)
                    ->validate()
                    ->execute();
            }
        }

        foreach ($this->paymentTypes as $paymentType) {
            if (! isset($paymentType['id'])) {
                $paymentType['tenants'] = [$tenant->id];
                CreatePaymentType::make($paymentType)
                    ->validate()
                    ->execute();
            }
        }

        foreach ($this->orderTypes as $orderTypeValue) {
            $orderType = OrderTypeEnum::from($orderTypeValue);

            if (
                resolve_static(OrderType::class, 'query')
                    ->where('order_type_enum', $orderType)
                    ->doesntExist()
            ) {
                CreateOrderType::make([
                    'tenant_id' => $tenant->id,
                    'name' => __($orderType->name),
                    'order_type_enum' => $orderType,
                ])
                    ->validate()
                    ->execute();
            }
        }

        if (
            resolve_static(PriceList::class, 'query')
                ->where('price_list_code', 'default')
                ->doesntExist()
        ) {
            CreatePriceList::make([
                'name' => __('Default'),
                'price_list_code' => 'default',
                'is_net' => true,
                'is_default' => true,
                'rounding_method_enum' => 'none',
            ])
                ->validate()
                ->execute();
        }

        if (resolve_static(Warehouse::class, 'query')->where('name', 'Default')->doesntExist()) {
            CreateWarehouse::make([
                'name' => __('Default'),
                'is_default' => true,
            ])
                ->validate()
                ->execute();
        }

        $this->call('flux:init-env', [
            'keyValues' => implode(',', [
                'app_locale:' . $this->languageData['language_code'],
                'app_name:' . $this->tenantData['name'],
                'flux_install_done:true',
            ]),
        ]);
    }

    protected function runInitialCommands(): void
    {
        info(__('Running initial setup commands...'));

        $commands = [
            ['init:permissions'],
            ['storage:link'],
            [VapidKeysGenerateCommand::class, ['--force' => true]],
            ['cache:clear'],
            ['route:clear'],
            ['view:clear'],
            ['config:clear'],
        ];

        foreach ($commands as $command) {
            $this->call($command[0], $command[1] ?? []);
        }
    }

    protected function setupTenant(): void
    {
        note(__('Company Information'));

        $existingTenants = resolve_static(Tenant::class, 'query')
            ->get([
                'id',
                'name',
                'tenant_code',
                'email',
            ]);

        if ($existingTenants->isNotEmpty()) {
            table(
                headers: [__('ID'), __('Name'), __('Code'), __('Email')],
                rows: $existingTenants->map(fn (Tenant $tenant) => [
                    $tenant->id,
                    $tenant->name,
                    $tenant->tenant_code,
                    $tenant->email,
                ])
                    ->toArray()
            );

            if (! confirm(__('Tenant already exists. Would you like to add another?'), false)) {
                $firstTenant = $existingTenants->first();
                $this->tenantData = [
                    'id' => $firstTenant->id,
                    'name' => $firstTenant->name,
                    'tenant_code' => $firstTenant->tenant_code,
                    'email' => $firstTenant->email,
                    'is_default' => $firstTenant->is_default,
                ];

                return;
            }
        }

        while (true) {
            $name = text(__('Company Name'), required: true);
            $tenantCode = text(__('Tenant Code'), required: true);
            $email = text(__('Company Email'), required: true);
            $phone = text(__('Company Phone'));
            $street = text(__('Street'));
            $postcode = text(__('Postcode'));
            $city = text(__('City'));

            $this->tenantData = [
                'name' => $name,
                'tenant_code' => $tenantCode,
                'email' => $email,
                'phone' => $phone ?: null,
                'street' => $street ?: null,
                'postcode' => $postcode ?: null,
                'city' => $city ?: null,
                'is_default' => true,
            ];

            try {
                CreateTenant::make($this->tenantData)->validate();

                break;
            } catch (ValidationException $e) {
                $this->error(__('Validation failed:'));

                foreach ($e->errors() as $errors) {
                    foreach ($errors as $error) {
                        $this->error("- $error");
                    }
                }

                info(__('Please try again.'));
            }
        }
    }

    protected function setupCurrency(): void
    {
        note(__('Currency Configuration'));

        $existingCurrencies = resolve_static(Currency::class, 'query')
            ->get([
                'id',
                'name',
                'iso',
                'symbol',
            ]);

        if ($existingCurrencies->isNotEmpty()) {
            table(
                headers: [__('ID'), __('Name'), __('ISO'), __('Symbol')],
                rows: $existingCurrencies->map(fn (object $curr): array => [$curr->id, $curr->name, $curr->iso, $curr->symbol])->toArray()
            );

            if (! confirm(__('Currency already exists. Would you like to add another?'), false)) {
                $firstCurrency = $existingCurrencies->first();
                $this->currencyData = [
                    'id' => $firstCurrency->id,
                    'name' => $firstCurrency->name,
                    'iso' => $firstCurrency->iso,
                    'symbol' => $firstCurrency->symbol,
                    'is_default' => $firstCurrency->is_default,
                ];

                return;
            }
        }

        while (true) {
            $name = text(__('Currency Name'), default: 'Euro');
            $isoCode = text(__('Currency ISO Code'), default: 'EUR');
            $symbol = text(__('Currency Symbol'), default: '€');

            $this->currencyData = [
                'name' => $name,
                'iso' => $isoCode,
                'symbol' => $symbol,
                'is_default' => true,
            ];

            try {
                CreateCurrency::make($this->currencyData)->validate();

                break;
            } catch (ValidationException $e) {
                $this->error(__('Validation failed:'));

                foreach ($e->errors() as $errors) {
                    foreach ($errors as $error) {
                        $this->error("- $error");
                    }
                }

                info(__('Please try again.'));
            }
        }
    }

    protected function setupDatabase(): void
    {
        $this->info(__('Running database migrations...'));

        try {
            $this->call('migrate', ['--force' => true]);
            $this->info(__('Database migrations completed!'));
            $this->line('');
        } catch (Exception $e) {
            $this->error(__('Database migration failed: ') . $e->getMessage());

            throw $e;
        }
    }

    protected function setupDataFromOptions(): void
    {
        $requiredFields = [
            'company-name',
            'company-email',
            'admin-firstname',
            'admin-lastname',
            'admin-email',
            'admin-password',
        ];

        foreach ($requiredFields as $field) {
            if (! $this->option($field)) {
                $this->error(__("Required field '--{$field}' is missing for non-interactive mode."));

                exit(1);
            }
        }

        $this->languageData = [
            'name' => $this->option('language-name'),
            'language_code' => $this->option('language-code'),
            'iso_name' => $this->option('language-code'),
        ];

        $this->currencyData = [
            'name' => $this->option('currency-name'),
            'iso' => $this->option('currency-iso'),
            'symbol' => $this->option('currency-symbol'),
            'is_default' => true,
        ];

        $this->tenantData = [
            'name' => $this->option('company-name'),
            'tenant_code' => $this->option('company-code')
                ?: strtoupper(substr($this->option('company-name'), 0, 3)),
            'email' => $this->option('company-email'),
            'phone' => $this->option('company-phone') ?: null,
            'street' => $this->option('company-street') ?: null,
            'postcode' => $this->option('company-postcode') ?: null,
            'city' => $this->option('company-city') ?: null,
            'is_default' => true,
        ];

        $vatRatesOption = $this->option('vat-rates');

        if ($vatRatesOption) {
            $vatRatesArray = explode(',', $vatRatesOption);

            foreach ($vatRatesArray as $index => $vatRate) {
                $parts = explode(':', $vatRate);
                if (count($parts) === 2) {
                    $this->vatRates[] = [
                        'name' => trim($parts[0]),
                        'rate_percentage' => bcdiv(trim($parts[1]), '100', 4),
                        'is_default' => $index === 0,
                    ];
                }
            }
        } else {
            $this->vatRates[] = [
                'name' => __('Standard'),
                'rate_percentage' => bcdiv('19', '100', 4),
                'is_default' => true,
            ];
        }

        $paymentTypesOption = $this->option('payment-types');
        if ($paymentTypesOption) {
            $paymentTypesArray = explode(',', $paymentTypesOption);

            foreach ($paymentTypesArray as $index => $paymentType) {
                $parts = explode(':', $paymentType);
                $this->paymentTypes[] = [
                    'name' => trim($parts[0]),
                    'description' => isset($parts[1]) ? trim($parts[1]) : null,
                    'is_active' => true,
                    'is_default' => $index === 0,
                ];
            }
        } else {
            $this->paymentTypes[] = [
                'name' => $this->option('payment-type-name'),
                'description' => $this->option('payment-type-description') ?: null,
                'is_active' => true,
                'is_default' => true,
            ];
        }

        $orderTypesOption = $this->option('order-types');
        if (! empty($orderTypesOption)) {
            $this->orderTypes = $orderTypesOption;
        } else {
            $this->orderTypes = array_map(fn (OrderTypeEnum $type): string => $type->value, OrderTypeEnum::cases());
        }

        $this->userData = [
            'firstname' => $this->option('admin-firstname'),
            'lastname' => $this->option('admin-lastname'),
            'email' => $this->option('admin-email'),
            'password' => $this->option('admin-password'),
            'user_code' => strtoupper(
                substr($this->option('admin-firstname'), 0, 1)
                . substr($this->option('admin-lastname'), 0, 1)
            ),
            'is_active' => true,
        ];
    }

    protected function setupLanguage(): void
    {
        note(__('Language Configuration'));

        $existingLanguages = resolve_static(Language::class, 'query')->get(['id', 'name', 'language_code']);
        if ($existingLanguages->isNotEmpty()) {
            table(
                headers: [__('ID'), __('Name'), __('Code')],
                rows: $existingLanguages->map(fn (object $lang): array => [$lang->id, $lang->name, $lang->language_code])->toArray()
            );

            if (! confirm(__('Language already exists. Would you like to add another?'), false)) {
                $firstLanguage = $existingLanguages->first();
                $this->languageData = [
                    'id' => $firstLanguage->id,
                    'name' => $firstLanguage->name,
                    'language_code' => $firstLanguage->language_code,
                    'iso_name' => $firstLanguage->language_code,
                ];

                return;
            }
        }

        while (true) {
            $languageCode = text(__('Language Code'), default: 'en');
            $languageName = text(__('Language Name'), default: 'English');

            $this->languageData = [
                'name' => $languageName,
                'language_code' => $languageCode,
                'iso_name' => $languageCode,
            ];

            try {
                CreateLanguage::make($this->languageData)->validate();

                break;
            } catch (ValidationException $e) {
                $this->error(__('Validation failed:'));

                foreach ($e->errors() as $field => $errors) {
                    foreach ($errors as $error) {
                        $this->error("- {$error}");
                    }
                }

                info(__('Please try again.'));
            }
        }

        app()->setLocale($this->languageData['language_code']);
    }

    protected function setupOrderTypes(): void
    {
        note(__('Order Types Configuration'));

        $existingOrderTypes = resolve_static(OrderType::class, 'query')
            ->pluck('order_type_enum')
            ->toArray();

        $orderTypeOptions = [];
        foreach (OrderTypeEnum::cases() as $orderType) {
            if (! in_array($orderType, $existingOrderTypes, true)) {
                $orderTypeOptions[$orderType->value] = __($orderType->name);
            }
        }

        if (empty($orderTypeOptions)) {
            info(__('All order types already exist.'));
            $this->orderTypes = [];

            return;
        }

        $selectedOrderTypes = multiselect(
            label: __('Select which order types to create'),
            options: $orderTypeOptions,
            default: array_keys($orderTypeOptions),
            required: true
        );

        $this->orderTypes = $selectedOrderTypes;
        info(__('Selected order types: ')
            . implode(
                ', ',
                array_map(fn (string $type): string => __($orderTypeOptions[$type]), $selectedOrderTypes)
            )
        );
    }

    protected function setupPaymentType(): void
    {
        note(__('Payment Type Configuration'));

        $existingPaymentTypes = resolve_static(PaymentType::class, 'query')
            ->get([
                'id',
                'name',
                'description',
            ]);

        if ($existingPaymentTypes->isNotEmpty()) {
            table(
                headers: [__('ID'), __('Name'), __('Description')],
                rows: $existingPaymentTypes->map(
                    fn (PaymentType $pt) => [
                        $pt->id, $pt->name, $pt->description ?: '-']
                )
                    ->toArray()
            );

            if (! confirm(__('Payment types already exist. Would you like to add more?'), false)) {
                $this->paymentTypes = $existingPaymentTypes->map(fn (PaymentType $pt): array => [
                    'id' => $pt->id,
                    'name' => $pt->name,
                    'description' => $pt->description,
                    'is_active' => $pt->is_active,
                    'is_default' => $pt->is_default,
                ])
                    ->toArray();

                return;
            }
        }

        while (true) {
            $name = text(__('Payment Type Name'), required: true);
            $description = text(__('Payment Type Description'));

            $paymentTypeData = [
                'name' => $name,
                'description' => $description ?: null,
                'is_active' => true,
                'is_default' => empty($this->paymentTypes) && $existingPaymentTypes->isEmpty(),
            ];

            $this->paymentTypes[] = $paymentTypeData;
            info(__('Payment Type added: ') . $name);

            if (! confirm(__('Add another payment type?'), false)) {
                break;
            }
        }
    }

    protected function setupUser(): void
    {
        note(__('Administrator Account'));

        $existingAdmins = resolve_static(User::class, 'query')
            ->whereRelation('roles', 'name', 'Super Admin')
            ->get();

        if ($existingAdmins->isNotEmpty()) {
            table(
                headers: [
                    __('ID'),
                    __('First Name'),
                    __('Last Name'),
                    __('Email'),
                ],
                rows: $existingAdmins->map(fn (User $user): array => [
                    $user->id,
                    $user->firstname,
                    $user->lastname,
                    $user->email]
                )
                    ->toArray()
            );

            if (! confirm(__('Super Admin users already exist. Would you like to add another?'), false)) {
                $firstAdmin = $existingAdmins->first();
                $this->userData = [
                    'id' => $firstAdmin->id,
                    'firstname' => $firstAdmin->firstname,
                    'lastname' => $firstAdmin->lastname,
                    'email' => $firstAdmin->email,
                ];

                return;
            }
        }

        while (true) {
            $firstName = text(__('First Name'), required: true);
            $lastName = text(__('Last Name'), required: true);
            $email = text(__('Email'), required: true);

            $timezones = timezone_identifiers_list();
            $defaultTimezone = config('app.timezone', 'UTC');

            $timezoneOptions = [];
            foreach ($timezones as $tz) {
                $timezoneOptions[$tz] = $tz;
            }

            $timezone = search(
                label: __('Timezone'),
                options: fn (string $search): array => strlen($search) > 0
                    ? array_filter(
                        $timezoneOptions,
                        fn (string $label) => str_contains(strtolower($label), strtolower($search))
                    )
                    : $timezoneOptions,
                placeholder: __('Search for a timezone...'),
                scroll: 10
            );

            while (true) {
                $password = password(__('Password'), required: true);
                $passwordConfirm = password(__('Confirm Password'), required: true);

                if ($password === $passwordConfirm) {
                    break;
                } else {
                    $this->error(__('Passwords do not match. Please try again.'));
                }
            }

            $this->userData = [
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
                'password' => $password,
                'timezone' => $timezone,
                'user_code' => strtoupper(
                    substr($firstName, 0, 1) . substr($lastName, 0, 1)
                ),
                'is_active' => true,
            ];

            try {
                CreateUser::make($this->userData)->validate();

                break;
            } catch (ValidationException $e) {
                $this->error(__('Validation failed:'));

                foreach ($e->errors() as $errors) {
                    foreach ($errors as $error) {
                        $this->error("- $error");
                    }
                }

                info(__('Please try again.'));
            }
        }
    }

    protected function setupVatRates(): void
    {
        note(__('VAT Rates Configuration'));

        $existingVatRates = resolve_static(VatRate::class, 'query')
            ->get([
                'id',
                'name',
                'rate_percentage',
            ]);

        if ($existingVatRates->isNotEmpty()) {
            table(
                headers: [__('ID'), __('Name'), __('Rate')],
                rows: $existingVatRates->map(fn (VatRate $vat) => [
                    $vat->id, $vat->name,
                    bcmul($vat->rate_percentage, '100', 2) . '%',
                ])
                    ->toArray()
            );

            if (! confirm(__('VAT rates already exist. Would you like to add more?'), false)) {
                $this->vatRates = $existingVatRates->map(fn (VatRate $vat) => [
                    'id' => $vat->id,
                    'name' => $vat->name,
                    'rate_percentage' => $vat->rate_percentage,
                    'is_default' => $vat->is_default,
                ])
                    ->toArray();

                return;
            }
        }

        while (true) {
            while (true) {
                $name = text(__('VAT Rate Name'), required: true);
                $rate = text(__('VAT Rate Percentage'), required: true);

                $vatRateData = [
                    'name' => $name,
                    'rate_percentage' => bcdiv($rate, '100', 4),
                    'is_default' => empty($this->vatRates) && $existingVatRates->isEmpty(),
                ];

                try {
                    CreateVatRate::make($vatRateData)->validate();
                    $this->vatRates[] = $vatRateData;
                    info(__('VAT Rate added: ') . $name . ' (' . $rate . '%)');
                    break;
                } catch (ValidationException $e) {
                    $this->error(__('Validation failed:'));

                    foreach ($e->errors() as $errors) {
                        foreach ($errors as $error) {
                            $this->error("- $error");
                        }
                    }

                    info(__('Please try again.'));
                }
            }

            if (! confirm(__('Add another VAT rate?'), false)) {
                break;
            }
        }
    }
}

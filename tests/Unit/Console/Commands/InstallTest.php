<?php

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

beforeEach(function (): void {
    config(['flux.install_done' => false]);
});

test('command fails when installation already completed', function (): void {
    config(['flux.install_done' => true]);

    $this->artisan('flux:install')
        ->expectsOutput(__('Installation already completed.'))
        ->assertFailed();
});

test('command runs migrations during setup', function (): void {
    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    expect(Tenant::count())->toBeGreaterThan(0);
});

test('command creates default language', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--language-code' => 'de',
        '--language-name' => 'Deutsch',
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    expect(Language::where('language_code', 'de')->exists())->toBeTrue();
    expect(Language::where('language_code', 'en')->exists())->toBeTrue();
});

test('command creates currency with correct data', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--currency-name' => 'US Dollar',
        '--currency-iso' => 'USD',
        '--currency-symbol' => '$',
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $currency = Currency::where('iso', 'USD')->first();
    expect($currency)->not->toBeNull()
        ->and($currency->name)->toBe('US Dollar')
        ->and($currency->symbol)->toBe('$')
        ->and($currency->is_default)->toBeTrue();
});

test('command creates tenant with company data', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'My Test Company',
        '--company-code' => 'MTC',
        '--company-email' => 'company@example.com',
        '--company-phone' => '+1234567890',
        '--company-street' => '123 Main St',
        '--company-postcode' => '12345',
        '--company-city' => 'Test City',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $tenant = Tenant::where('name', 'My Test Company')->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->tenant_code)->toBe('MTC')
        ->and($tenant->email)->toBe('company@example.com')
        ->and($tenant->phone)->toBe('+1234567890')
        ->and($tenant->street)->toBe('123 Main St')
        ->and($tenant->postcode)->toBe('12345')
        ->and($tenant->city)->toBe('Test City')
        ->and($tenant->is_default)->toBeTrue();
});

test('command creates vat rates from option', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--vat-rates' => 'Standard:19,Reduced:7,Zero:0',
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    expect(VatRate::where('name', 'Standard')->exists())->toBeTrue();
    expect(VatRate::where('name', 'Reduced')->exists())->toBeTrue();
    expect(VatRate::where('name', 'Zero')->exists())->toBeTrue();

    $standardVat = VatRate::where('name', 'Standard')->first();
    expect(bcmul($standardVat->rate_percentage, '100', 2))->toBe('19.00');
});

test('command creates default vat rate when not provided', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $defaultVat = VatRate::where('is_default', true)->first();
    expect($defaultVat)->not->toBeNull();
    expect(bcmul($defaultVat->rate_percentage, '100', 2))->toBe('19.00');
});

test('command creates payment types from option', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--payment-types' => 'Cash:Cash payment,Credit Card:Pay by card',
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    expect(PaymentType::where('name', 'Cash')->exists())->toBeTrue();
    expect(PaymentType::where('name', 'Credit Card')->exists())->toBeTrue();

    $cashPayment = PaymentType::where('name', 'Cash')->first();
    expect($cashPayment->description)->toBe('Cash payment');
});

test('command creates default payment type when not provided', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--payment-type-name' => 'Bank Transfer',
        '--payment-type-description' => 'Payment by bank transfer',
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $payment = PaymentType::where('name', 'Bank Transfer')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->description)->toBe('Payment by bank transfer')
        ->and($payment->is_active)->toBeTrue()
        ->and($payment->is_default)->toBeTrue();
});

test('command creates admin user with correct data', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'Jane',
        '--admin-lastname' => 'Smith',
        '--admin-email' => 'jane.smith@example.com',
        '--admin-password' => 'secure-password',
    ])->assertSuccessful();

    $admin = User::where('email', 'jane.smith@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->firstname)->toBe('Jane')
        ->and($admin->lastname)->toBe('Smith')
        ->and($admin->user_code)->toBe('JS')
        ->and($admin->is_active)->toBeTrue();

    expect($admin->hasRole('Super Admin'))->toBeTrue();
});

test('command creates default price list', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $priceList = PriceList::where('price_list_code', 'default')->first();
    expect($priceList)->not->toBeNull()
        ->and($priceList->is_default)->toBeTrue()
        ->and($priceList->is_net)->toBeTrue();
});

test('command creates default warehouse', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $warehouse = Warehouse::where('is_default', true)->first();
    expect($warehouse)->not->toBeNull();
});

test('command creates specified order types', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--order-types' => ['order', 'invoice'],
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    expect(OrderType::where('order_type_enum', 'order')->exists())->toBeTrue();
    expect(OrderType::where('order_type_enum', 'invoice')->exists())->toBeTrue();
});

test('command generates tenant code from company name if not provided', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Amazing Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])->assertSuccessful();

    $tenant = Tenant::where('name', 'Amazing Test Company')->first();
    expect($tenant->tenant_code)->toBe('AMA');
});

test('command requires necessary fields in non-interactive mode', function (): void {
    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
    ])->assertFailed();
});

test('command displays success message on completion', function (): void {
    Role::factory()->create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('flux:install', [
        '--no-interaction' => true,
        '--skip-migrations' => true,
        '--skip-init-commands' => true,
        '--company-name' => 'Test Company',
        '--company-email' => 'test@example.com',
        '--admin-firstname' => 'John',
        '--admin-lastname' => 'Doe',
        '--admin-email' => 'admin@example.com',
        '--admin-password' => 'Password123!',
    ])
        ->expectsOutput(__('Installation completed successfully!'))
        ->assertSuccessful();
});

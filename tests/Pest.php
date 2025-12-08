<?php

use FluxErp\Models\Currency;
use FluxErp\Models\Language;
use FluxErp\Models\PaymentType;
use FluxErp\Models\PriceList;
use FluxErp\Models\Tenant;
use FluxErp\Models\User;
use FluxErp\Models\VatRate;
use FluxErp\Settings\CoreSettings;
use TeamNiftyGmbH\FluxLicense\Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->beforeEach(function (): void {
        config(['app.debug' => true]);
        CoreSettings::fake([
            'install_done' => false,
            'license_key' => 'test-license-key-12345',
            'formal_salutation' => false,
        ]);

        PriceList::default() ?? PriceList::factory()->create(['is_default' => true]);
        $this->dbTenant = Tenant::default() ?? Tenant::factory()->create(['is_default' => true]);
        $this->defaultLanguage = Language::default() ?? Language::factory()->create(['is_default' => true]);
        VatRate::default() ?? VatRate::factory()->create(['is_default' => true]);

        PaymentType::default() ?? PaymentType::factory()
            ->hasAttached($this->dbTenant, relationship: 'tenants')
            ->create(['is_active' => true, 'is_default' => true, 'is_sales' => true]);

        Currency::default() ?? Currency::factory()->create(['is_default' => true]);

        $this->user = User::factory()->create([
            'is_active' => true,
            'language_id' => $this->defaultLanguage->getKey(),
        ]);

        if (! auth()->user()) {
            $this->be($this->user, 'web');
        }

        $this->withoutVite();
    })
    ->in('Feature', 'Unit');

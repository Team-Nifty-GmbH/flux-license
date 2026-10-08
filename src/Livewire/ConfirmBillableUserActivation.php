<?php

namespace TeamNiftyGmbH\FluxLicense\Livewire;

use FluxErp\Livewire\Settings\UserEdit;
use FluxErp\Livewire\Settings\Users;
use FluxErp\Models\User;
use Illuminate\Support\Number;
use Livewire\ComponentHook;
use TeamNiftyGmbH\FluxLicense\Support\LicensePricing;

/**
 * Intercepts save() on the user settings screens: activating a user that adds to the
 * license bill has to be confirmed first. The dialog's confirm button calls save()
 * again with CONFIRMED, which lets the call through and marks the request, so the
 * activation gets reported as confirmed in the ui.
 */
class ConfirmBillableUserActivation extends ComponentHook
{
    public const CONFIRMED = 'flux-license:confirmed';

    public function call(string $method, array $params, callable $returnEarly): void
    {
        if ($method !== 'save'
            || ! ($this->component instanceof Users || $this->component instanceof UserEdit)
            || ! $this->activatesUser()
        ) {
            return;
        }

        if (data_get($params, 0) === self::CONFIRMED) {
            request()->attributes->set(self::CONFIRMED, true);

            return;
        }

        $pricing = LicensePricing::get();
        $unitPrice = data_get($pricing, 'unit_price');

        if (! is_null($unitPrice)
            && ! LicensePricing::addsToBill($pricing, User::query()->where('is_active', true)->count())
        ) {
            return;
        }

        $this->component->dialog()
            ->question(
                __('Activate user?'),
                is_null($unitPrice)
                    // Unknown price still needs a confirmation, the dialog just cannot name it
                    ? __('Activating this user can increase the license costs.')
                    : __('Activating this user adds :price net per month to the license costs.', [
                        'price' => Number::currency($unitPrice, data_get($pricing, 'currency') ?? 'EUR', app()->getLocale()),
                    ])
            )
            ->confirm(__('Activate'), 'save', self::CONFIRMED)
            ->cancel(__('Cancel'))
            ->send();

        $returnEarly(false);
    }

    protected function activatesUser(): bool
    {
        $form = $this->component->userForm;

        return $form->is_active
            && (! $form->id || ! User::query()->whereKey($form->id)->value('is_active'));
    }
}

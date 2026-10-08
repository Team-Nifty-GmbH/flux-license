<?php

namespace TeamNiftyGmbH\FluxLicense\Livewire;

use FluxErp\Livewire\Settings\UserEdit;
use FluxErp\Livewire\Settings\Users;
use FluxErp\Models\User;
use FluxErp\Settings\CoreSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Number;
use Livewire\ComponentHook;
use Throwable;

/**
 * Intercepts save() on the user settings screens: activating a user that adds to the
 * license bill has to be confirmed first. The dialog's confirm button calls save()
 * again with CONFIRMED, which lets the call through.
 */
class ConfirmBillableUserActivation extends ComponentHook
{
    public const CONFIRMED = 'flux-license:confirmed';

    public function call(string $method, array $params, callable $returnEarly): void
    {
        if ($method !== 'save'
            || data_get($params, 0) === self::CONFIRMED
            || ! ($this->component instanceof Users || $this->component instanceof UserEdit)
            || ! $this->activatesUser()
        ) {
            return;
        }

        $pricing = $this->pricing();
        $unitPrice = data_get($pricing, 'unit_price');

        if (! is_null($unitPrice) && ! $this->addsToBill($pricing)) {
            return;
        }

        $this->component->dialog()
            ->question(
                __('Activate user?'),
                is_null($unitPrice)
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

    // Mirrors the billed amount FluxLicense::updateOrder() computes on the license server
    protected function addsToBill(array $pricing): bool
    {
        $billed = fn (int $users): int => min(
            max(max($users, $pricing['min_accounts']) - $pricing['free_accounts'], 0),
            $pricing['max_accounts']
        );

        $active = User::query()->where('is_active', true)->count();

        return $billed($active + 1) > $billed($active);
    }

    protected function pricing(): ?array
    {
        try {
            return Cache::remember('flux-license:pricing', now()->addDay(), fn (): array => Http::timeout(5)
                ->acceptJson()
                ->get('https://flux.team-nifty.com/api/flux-licenses/' . app(CoreSettings::class)->license_key . '/pricing')
                ->throw()
                ->json()
            );
        } catch (Throwable) {
            // Unknown price still needs a confirmation, the dialog just cannot name it
            return null;
        }
    }
}

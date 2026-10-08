<?php

use FluxErp\Livewire\Settings\UserEdit;
use FluxErp\Livewire\Settings\Users;
use FluxErp\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use TeamNiftyGmbH\FluxLicense\Jobs\ReportUserActivation;

function fakeLicensePricing(array $pricing = []): void
{
    Http::fake([
        'flux.team-nifty.com/api/flux-licenses/test-license-key-12345/pricing' => Http::response(array_merge([
            'unit_price' => '35.00',
            'currency' => 'EUR',
            'free_accounts' => 0,
            'min_accounts' => 1,
            'max_accounts' => 999,
        ], $pricing)),
        'flux.team-nifty.com/*' => Http::response(),
    ]);
}

function newUserForm(bool $isActive = true): Testable
{
    return Livewire::test(Users::class)
        ->set('userForm.firstname', 'Jane')
        ->set('userForm.lastname', 'Doe')
        ->set('userForm.email', 'jane@example.com')
        ->set('userForm.password', 'Password123!')
        ->set('userForm.user_code', 'JD')
        ->set('userForm.language_id', test()->defaultLanguage->getKey())
        ->set('userForm.is_active', $isActive);
}

test('creating a billable active user asks for confirmation and saves nothing', function (): void {
    fakeLicensePricing();

    newUserForm()
        ->call('save')
        ->assertDispatched(
            'ts-ui:dialog',
            fn (string $name, array $params) => str_contains($params['description'], '35.00')
                && $params['options']['confirm']['method'] === 'save'
        );

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
});

test('the confirmed save creates the user', function (): void {
    fakeLicensePricing();

    newUserForm()
        ->call('save', 'flux-license:confirmed')
        ->assertNotDispatched('ts-ui:dialog');

    expect(User::query()->where('email', 'jane@example.com')->value('is_active'))->toBeTrue();
});

test('a user within the free accounts needs no confirmation', function (): void {
    fakeLicensePricing(['free_accounts' => 5]);

    newUserForm()
        ->call('save')
        ->assertNotDispatched('ts-ui:dialog');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('an inactive user needs no confirmation', function (): void {
    fakeLicensePricing();

    newUserForm(isActive: false)
        ->call('save')
        ->assertNotDispatched('ts-ui:dialog');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('without pricing the activation still has to be confirmed', function (): void {
    Http::fake([
        'flux.team-nifty.com/api/flux-licenses/test-license-key-12345/pricing' => Http::response(status: 500),
        'flux.team-nifty.com/*' => Http::response(),
    ]);

    newUserForm()
        ->call('save')
        ->assertDispatched('ts-ui:dialog');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeFalse();
});

test('activating an inactive user on the edit page asks for confirmation', function (): void {
    fakeLicensePricing();
    $user = User::factory()->create(['is_active' => false, 'language_id' => $this->defaultLanguage->getKey()]);

    Livewire::test(UserEdit::class, ['user' => $user])
        ->set('userForm.is_active', true)
        ->call('save')
        ->assertDispatched('ts-ui:dialog');

    expect($user->refresh()->is_active)->toBeFalse();
});

test('saving an already active user on the edit page needs no confirmation', function (): void {
    fakeLicensePricing();
    $user = User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);

    Livewire::test(UserEdit::class, ['user' => $user])
        ->set('userForm.firstname', 'Changed')
        ->call('save')
        ->assertNotDispatched('ts-ui:dialog');

    expect($user->refresh()->firstname)->toBe('Changed');
});

test('the confirmed activation is reported with who confirmed it', function (): void {
    fakeLicensePricing();
    Queue::fake();
    $this->travelTo('2026-10-08 09:15:00');

    newUserForm()
        ->call('save', 'flux-license:confirmed');

    Queue::assertPushed(ReportUserActivation::class, fn (ReportUserActivation $job): bool => $job->data === [
        'activated_user_email' => 'jane@example.com',
        'activated_user_name' => User::query()->where('email', 'jane@example.com')->value('name'),
        'confirmed_by_email' => $this->user->email,
        'confirmed_by_name' => $this->user->name,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Symfony',
        'unit_price' => '35.00',
        'currency' => 'EUR',
        'confirmed_at' => '2026-10-08T09:15:00+00:00',
    ]);
});

test('nothing is reported while the activation is not confirmed', function (): void {
    fakeLicensePricing();
    Queue::fake();

    newUserForm()->call('save');

    Queue::assertNotPushed(ReportUserActivation::class);
});

test('nothing is reported when the confirmed save fails', function (): void {
    fakeLicensePricing();
    Queue::fake();

    newUserForm()
        ->set('userForm.email', 'not-an-email')
        ->call('save', 'flux-license:confirmed');

    Queue::assertNotPushed(ReportUserActivation::class);
});

test('the report is sent to the license server', function (): void {
    Http::fake(['flux.team-nifty.com/*' => Http::response(status: 201)]);

    (new ReportUserActivation(['activated_user_email' => 'jane@example.com']))->handle();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://flux.team-nifty.com/api/flux-licenses/test-license-key-12345/user-activations'
        && $request['activated_user_email'] === 'jane@example.com'
    );
});

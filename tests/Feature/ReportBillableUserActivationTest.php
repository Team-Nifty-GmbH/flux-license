<?php

use FluxErp\Actions\User\CreateUser;
use FluxErp\Actions\User\UpdateUser;
use FluxErp\Models\Permission;
use FluxErp\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use TeamNiftyGmbH\FluxLicense\Jobs\ReportUserActivation;

beforeEach(function (): void {
    Queue::fake([ReportUserActivation::class]);
});

function fakePricing(array $pricing = []): void
{
    Http::fake([
        'flux.team-nifty.com/api/flux-licenses/test-license-key-12345/pricing' => Http::response(array_merge([
            'unit_price' => '35.00',
            'currency' => 'EUR',
            'free_accounts' => 0,
            'min_accounts' => 1,
            'max_accounts' => 999,
        ], $pricing)),
        'flux.team-nifty.com/*' => Http::response(status: 201),
    ]);
}

function newUserData(array $data = []): array
{
    return array_merge([
        'firstname' => 'Api',
        'lastname' => 'User',
        'email' => 'api@example.com',
        'password' => 'Password123!',
        'user_code' => 'AU',
        'language_id' => test()->defaultLanguage->getKey(),
        'is_active' => true,
    ], $data);
}

function reportsSent(): array
{
    return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/user-activations'))->values()->all();
}

test('an activation through the api is reported with its source', function (): void {
    fakePricing();
    $this->user->givePermissionTo(Permission::findOrCreate('api.users.post', 'sanctum'));
    Sanctum::actingAs($this->user, ['user']);

    $this->postJson('/api/users', newUserData())->assertSuccessful();

    Queue::assertPushed(ReportUserActivation::class, fn (ReportUserActivation $job): bool => $job->data['source'] === 'api'
        && $job->data['activated_user_email'] === 'api@example.com'
        && $job->data['confirmed_by_email'] === $this->user->email
    );
});

test('an activation through the action outside a request is reported as console', function (): void {
    fakePricing();
    CreateUser::make(newUserData())->validate()->execute();

    Queue::assertPushed(ReportUserActivation::class, fn (ReportUserActivation $job): bool => $job->data['source'] === 'console'
        && $job->data['activated_user_email'] === 'api@example.com'
        && $job->activeUsersBefore === 1
    );
});

test('reactivating an inactive user is reported', function (): void {
    fakePricing();
    $user = User::factory()->create(['is_active' => false, 'language_id' => $this->defaultLanguage->getKey()]);

    UpdateUser::make(['id' => $user->getKey(), 'is_active' => true])->validate()->execute();

    Queue::assertPushed(ReportUserActivation::class, fn (ReportUserActivation $job): bool => $job->data['activated_user_email'] === $user->email);
});

test('updating an already active user is not reported', function (): void {
    fakePricing();
    $user = User::factory()->create(['is_active' => true, 'language_id' => $this->defaultLanguage->getKey()]);
    Queue::fake([ReportUserActivation::class]);

    UpdateUser::make(['id' => $user->getKey(), 'firstname' => 'Changed'])->validate()->execute();

    Queue::assertNotPushed(ReportUserActivation::class);
});

test('creating an inactive user is not reported', function (): void {
    fakePricing();
    CreateUser::make(newUserData(['is_active' => false]))->validate()->execute();

    Queue::assertNotPushed(ReportUserActivation::class);
});

test('the job sends a billable activation with its price', function (): void {
    fakePricing();

    (new ReportUserActivation(['source' => 'api', 'activated_user_email' => 'api@example.com'], 1))->handle();

    expect(reportsSent())->toHaveCount(1)
        ->and(reportsSent()[0][0]->url())->toBe('https://flux.team-nifty.com/api/flux-licenses/test-license-key-12345/user-activations')
        ->and(reportsSent()[0][0]->data())->toMatchArray([
            'source' => 'api',
            'activated_user_email' => 'api@example.com',
            'unit_price' => '35.00',
            'currency' => 'EUR',
        ]);
});

test('the job skips an activation within the free accounts', function (): void {
    fakePricing(['free_accounts' => 5]);

    (new ReportUserActivation(['source' => 'api', 'activated_user_email' => 'api@example.com'], 1))->handle();

    expect(reportsSent())->toBeEmpty();
});

test('the job sends the activation when the price is unknown', function (): void {
    Http::fake([
        'flux.team-nifty.com/api/flux-licenses/test-license-key-12345/pricing' => Http::response(status: 500),
        'flux.team-nifty.com/*' => Http::response(status: 201),
    ]);

    (new ReportUserActivation(['source' => 'api', 'activated_user_email' => 'api@example.com'], 1))->handle();

    expect(reportsSent())->toHaveCount(1)
        ->and(reportsSent()[0][0]['unit_price'])->toBeNull();
});

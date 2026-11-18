<?php

use App\Actions\Sharing\StorePendingSharedClaim;
use App\Http\Middleware\EnsureRecentLogin;
use App\Http\Responses\PasskeyLoginResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

it('F-02: lists the saved passkeys on the settings page', function () {
    $user = User::factory()->create();
    createPasskeyFor($user, 'MacBook');

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings')
            ->has('settings.passkeys', 1)
            ->where('settings.passkeys.0.name', 'MacBook')
            ->where('settings.passkeys.0.last_used_at', null));
});

it('F-02: offers passkey registration to a guest without email, password or passkey, without a recent login', function () {
    $user = User::factory()->create(['email' => null, 'password' => null]);

    $this->actingAs($user)
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
});

it('F-02: removes a passkey and flashes feedback', function () {
    $user = User::factory()->create();
    $passkey = createPasskeyFor($user);

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->timestamp])
        ->delete(route('passkey.destroy', $passkey))
        ->assertRedirect()
        ->assertSessionHas('success', 'Passkey removed.');

    expect($user->passkeys()->count())->toBe(0);
});

it('F-02: does not remove the passkey of another account', function () {
    $owner = User::factory()->create();
    $passkey = createPasskeyFor($owner);

    $this->actingAs(User::factory()->create())
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->timestamp])
        ->delete(route('passkey.destroy', $passkey))
        ->assertForbidden();

    expect($owner->passkeys()->count())->toBe(1);
});

it('F-01: gives a guest passkey login options', function () {
    $this->getJson(route('passkey.login-options'))
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge']]);
});

it('F-01: rejects a passkey login without a credential', function () {
    $this->postJson(route('passkey.login'))
        ->assertUnprocessable();

    $this->assertGuest();
});

it('F-01: sends a passkey login to the dashboard', function () {
    $user = User::factory()->create();
    $request = Request::create(route('passkey.login'), 'POST');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));

    $response = app(PasskeyLoginResponse::class)->toResponse($request);

    expect($response->getData(true))->toBe(['redirect' => route('dashboard')]);
});

it('F-02: G-4 a blocked passkey removal redirects back with the message', function () {
    $user = User::factory()->create();
    $passkey = createPasskeyFor($user);

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->subMinutes(16)->timestamp])
        ->delete(route('passkey.destroy', $passkey))
        ->assertRedirect()
        ->assertSessionHas('error', __('ui.settings.passkeys.recentLogin', ['minutes' => 15]));

    expect($user->passkeys()->count())->toBe(1);
});

it('F-19: ignores an invalid pending claim on a passkey login', function () {
    $user = User::factory()->create();
    $request = Request::create(route('passkey.login'), 'POST', ['pending_claim_roster_token' => 'x', 'pending_claim_item_id' => 1, 'pending_claim_quantity' => -3]);
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));

    $response = app(PasskeyLoginResponse::class)->toResponse($request);

    expect($response->getData(true))->toBe(['redirect' => route('dashboard')])
        ->and(session()->has(StorePendingSharedClaim::ACTIONS_KEY))->toBeFalse();
});

it('F-02: binds the passkey relying party to a host outside the APP_URL domain', function () {
    config([
        'app.url' => 'https://www.example.test',
        'passkeys.relying_party_id' => 'www.example.test',
        'passkeys.allowed_hosts' => ['example.test'],
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->timestamp])
        ->getJson('https://example.test/user/passkeys/options')
        ->assertOk()
        ->assertJsonPath('options.rp.id', 'example.test');
});

it('F-02: keeps the configured passkey relying party for a host that is not allowed', function () {
    config(['app.url' => 'https://www.example.test', 'passkeys.relying_party_id' => 'www.example.test']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->timestamp])
        ->getJson('https://evil.test/user/passkeys/options')
        ->assertOk()
        ->assertJsonPath('options.rp.id', 'www.example.test');
});

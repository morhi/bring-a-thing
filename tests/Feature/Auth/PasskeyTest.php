<?php

use App\Http\Middleware\EnsureRecentLogin;
use App\Http\Responses\PasskeyLoginResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passkeys\Passkey;

/**
 * Store a passkey row for the user without running a WebAuthn ceremony.
 */
function createPasskeyFor(User $user, string $name = 'MacBook'): Passkey
{
    return $user->passkeys()->forceCreate([
        'name' => $name,
        'credential_id' => fake()->unique()->sha1(),
        'credential' => ['type' => 'public-key'],
    ]);
}

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

it('F-02: offers passkey registration to an account without a password', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->timestamp])
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

it('F-02: blocks passkey changes when the login is older than 15 minutes', function () {
    $user = User::factory()->create();
    $passkey = createPasskeyFor($user);
    $stale = [EnsureRecentLogin::SESSION_KEY => now()->subMinutes(16)->timestamp];

    $this->actingAs($user)->withSession($stale)
        ->getJson(route('passkey.registration-options'))
        ->assertForbidden();

    $this->actingAs($user)->withSession($stale)
        ->delete(route('passkey.destroy', $passkey))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($user->passkeys()->count())->toBe(1);
});

it('F-02: records the login time when a session logs in', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $this->post(route('login.password'), ['email' => $user->email, 'password' => 'correct-password']);

    expect(session(EnsureRecentLogin::SESSION_KEY))->toBeGreaterThanOrEqual(now()->subMinute()->timestamp);
});

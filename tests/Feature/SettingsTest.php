<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects guests away from the settings page', function () {
    $response = $this->get(route('settings.edit'));

    $response->assertRedirect(route('login'));
});

it('allows a user to set their own name', function () {
    $user = User::factory()->shadow()->create();

    $response = $this->actingAs($user)->patch(route('settings.profile'), [
        'name' => 'Jane Doe',
    ]);

    $response->assertRedirect();
    expect($user->fresh()->name)->toBe('Jane Doe');
});

it('requires a name when updating the profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('settings.profile'), [
        'name' => '',
    ]);

    $response->assertInvalid('name');
});

it('allows a user without a password to set one', function () {
    $user = User::factory()->shadow()->create();

    $response = $this->actingAs($user)->patch(route('settings.password'), [
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ]);

    $response->assertRedirect();
    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue();
});

it('requires the current password to change an existing password', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $response = $this->actingAs($user)->patch(route('settings.password'), [
        'current_password' => 'wrong-password',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ]);

    $response->assertInvalid('current_password');
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('changes the password when the current password is correct', function () {
    $user = User::factory()->create(['password' => 'old-password']);

    $response = $this->actingAs($user)->patch(route('settings.password'), [
        'current_password' => 'old-password',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ]);

    $response->assertRedirect();
    expect(Hash::check('a-new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects a password change when the confirmation does not match', function () {
    $user = User::factory()->shadow()->create();

    $response = $this->actingAs($user)->patch(route('settings.password'), [
        'password' => 'a-new-password',
        'password_confirmation' => 'does-not-match',
    ]);

    $response->assertInvalid('password');
});

it('F-02: shows the settings page with the account state', function () {
    $user = User::factory()->shadow()->create(['name' => 'Jo', 'email' => null, 'pending_email' => 'jo@example.com']);

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings')
            ->where('settings.name', 'Jo')
            ->where('settings.email', null)
            ->where('settings.pending_email', 'jo@example.com')
            ->where('settings.has_password', false)
            ->where('settings.languages', ['en', 'de'])
            ->where('settings.password_min_length', 8));
});

it('F-02: after setting a password, login with email and password succeeds', function () {
    $user = User::factory()->shadow()->create(['email' => 'pw@example.com']);

    $this->actingAs($user)
        ->patch(route('settings.password'), [
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])
        ->assertSessionHas('success', __('ui.settings.password.setDone'));

    auth()->logout();

    $this->post(route('login.password'), ['email' => 'pw@example.com', 'password' => 'a-new-password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

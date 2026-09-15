<?php

use App\Models\User;

it('logs in a user with a correct password', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects a login with an incorrect password', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertInvalid('email');
    $this->assertGuest();
});

it('rejects a password login for a user who has no password set', function () {
    $user = User::factory()->shadow()->create();

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'anything',
    ]);

    $response->assertInvalid('email');
    $this->assertGuest();
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

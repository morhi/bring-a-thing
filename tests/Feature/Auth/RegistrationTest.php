<?php

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('registers a new user by name and email and sends a magic link without requiring a password', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), ['name' => 'New Comer', 'email' => 'newcomer@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'name' => 'New Comer',
        'email' => 'newcomer@example.com',
        'password' => null,
    ]);
    Mail::assertQueued(MagicLinkMail::class);
    $this->assertGuest();
});

it('requires a name to register', function () {
    $response = $this->post(route('register.store'), ['email' => 'newcomer@example.com']);

    $response->assertInvalid('name');
});

it('reuses an existing shadow account and backfills its name when registering with an already-invited email', function () {
    Mail::fake();
    $shadowUser = User::factory()->shadow()->create(['email' => 'invited@example.com']);

    $this->post(route('register.store'), ['name' => 'Invited Person', 'email' => 'invited@example.com']);

    expect(User::query()->where('email', 'invited@example.com')->count())->toBe(1);
    expect($shadowUser->fresh()->name)->toBe('Invited Person');
    Mail::assertQueued(MagicLinkMail::class, fn ($mail) => $mail->hasTo($shadowUser->email));
});

it('rejects registration with an invalid email', function () {
    $response = $this->post(route('register.store'), ['name' => 'Someone', 'email' => 'invalid']);

    $response->assertInvalid('email');
});

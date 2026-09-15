<?php

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('registers a new user by email and sends a magic link without requiring a password', function () {
    Mail::fake();

    $response = $this->post(route('register.store'), ['email' => 'newcomer@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'email' => 'newcomer@example.com',
        'password' => null,
    ]);
    Mail::assertQueued(MagicLinkMail::class);
    $this->assertGuest();
});

it('reuses an existing shadow account when registering with an already-invited email', function () {
    Mail::fake();
    $shadowUser = User::factory()->shadow()->create(['email' => 'invited@example.com']);

    $this->post(route('register.store'), ['email' => 'invited@example.com']);

    expect(User::query()->where('email', 'invited@example.com')->count())->toBe(1);
    Mail::assertQueued(MagicLinkMail::class, fn ($mail) => $mail->hasTo($shadowUser->email));
});

it('rejects registration with an invalid email', function () {
    $response = $this->post(route('register.store'), ['email' => 'invalid']);

    $response->assertInvalid('email');
});

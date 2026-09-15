<?php

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('creates a shadow user and sends a magic link when requesting a link for a new email', function () {
    Mail::fake();

    $response = $this->post(route('magic-link.store'), ['email' => 'new@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'email' => 'new@example.com',
        'name' => null,
        'password' => null,
    ]);
    Mail::assertQueued(MagicLinkMail::class);
});

it('reuses the existing user when requesting a magic link for a known email', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'known@example.com']);

    $this->post(route('magic-link.store'), ['email' => 'known@example.com']);

    expect(User::query()->where('email', 'known@example.com')->count())->toBe(1);
    Mail::assertQueued(MagicLinkMail::class, fn ($mail) => $mail->hasTo($user->email));
});

it('rejects a magic link request with an invalid email', function () {
    $response = $this->post(route('magic-link.store'), ['email' => 'not-an-email']);

    $response->assertInvalid('email');
});

it('logs in the user when visiting a valid signed magic link', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects an expired magic link', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute('login.consume', now()->subMinute(), ['user' => $user->id]);

    $response = $this->get($url);

    $response->assertForbidden();
    $this->assertGuest();
});

it('rejects a tampered magic link', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id]);
    $tamperedUrl = str_replace((string) $user->id, (string) $other->id, $url);

    $response = $this->get($tamperedUrl);

    $response->assertForbidden();
    $this->assertGuest();
});

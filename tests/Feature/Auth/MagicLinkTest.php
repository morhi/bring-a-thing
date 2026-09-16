<?php

use App\Mail\MagicLinkMail;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('creates a shadow user and sends a magic link when requesting a link for a new email with a name', function () {
    Mail::fake();

    $response = $this->post(route('magic-link.store'), ['name' => 'New Person', 'email' => 'new@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('users', [
        'email' => 'new@example.com',
        'name' => 'New Person',
        'password' => null,
    ]);
    Mail::assertQueued(MagicLinkMail::class);
});

it('requires a name when requesting a magic link for a new email', function () {
    $response = $this->post(route('magic-link.store'), ['email' => 'new@example.com']);

    $response->assertInvalid('name');
});

it('reuses the existing user when requesting a magic link for a known email with a name, without needing a name', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'known@example.com']);

    $response = $this->post(route('magic-link.store'), ['email' => 'known@example.com']);

    $response->assertValid('name');
    expect(User::query()->where('email', 'known@example.com')->count())->toBe(1);
    Mail::assertQueued(MagicLinkMail::class, fn ($mail) => $mail->hasTo($user->email));
});

it('backfills a name for an existing nameless shadow account requesting a magic link', function () {
    Mail::fake();
    $user = User::factory()->shadow()->create(['email' => 'shadow@example.com']);

    $response = $this->post(route('magic-link.store'), ['name' => 'Shadow Person', 'email' => 'shadow@example.com']);

    $response->assertRedirect();
    expect($user->fresh()->name)->toBe('Shadow Person');
});

it('rejects a magic link request with an invalid email', function () {
    $response = $this->post(route('magic-link.store'), ['name' => 'Someone', 'email' => 'not-an-email']);

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

it('reports that a name is needed for an unknown email', function () {
    $response = $this->getJson(route('magic-link.needs-name', ['email' => 'new@example.com']));

    $response->assertOk()->assertJson(['needsName' => true]);
});

it('reports that a name is needed for an existing nameless shadow account', function () {
    User::factory()->shadow()->create(['email' => 'shadow@example.com']);

    $response = $this->getJson(route('magic-link.needs-name', ['email' => 'shadow@example.com']));

    $response->assertOk()->assertJson(['needsName' => true]);
});

it('reports that a name is not needed for an existing user with a name', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);

    $response = $this->getJson(route('magic-link.needs-name', ['email' => $user->email]));

    $response->assertOk()->assertJson(['needsName' => false]);
});

it('reports that a password is needed for an account that has one set', function () {
    $user = User::factory()->create(['email' => 'has-password@example.com']);

    $response = $this->getJson(route('magic-link.needs-name', ['email' => $user->email]));

    $response->assertOk()->assertJson(['hasPassword' => true]);
});

it('reports that no password is needed for a shadow account without one', function () {
    $user = User::factory()->shadow()->create(['email' => 'no-password@example.com']);

    $response = $this->getJson(route('magic-link.needs-name', ['email' => $user->email]));

    $response->assertOk()->assertJson(['hasPassword' => false]);
});

it('reports that no password is needed for an unknown email', function () {
    $response = $this->getJson(route('magic-link.needs-name', ['email' => 'unknown@example.com']));

    $response->assertOk()->assertJson(['hasPassword' => false]);
});

it('creates a first standalone roster when a roster name is submitted with the onboarding request', function () {
    Mail::fake();

    $response = $this->post(route('magic-link.store'), [
        'name' => 'New Person',
        'email' => 'onboarding@example.com',
        'roster_name' => 'Sunday Potluck',
    ]);

    $response->assertRedirect();
    $user = User::query()->where('email', 'onboarding@example.com')->firstOrFail();
    $this->assertDatabaseHas('rosters', [
        'title' => 'Sunday Potluck',
        'owner_id' => $user->id,
    ]);
});

it('does not create a roster when no roster name is submitted', function () {
    Mail::fake();

    $this->post(route('magic-link.store'), ['name' => 'New Person', 'email' => 'no-roster@example.com']);

    $user = User::query()->where('email', 'no-roster@example.com')->firstOrFail();
    expect(Roster::query()->where('owner_id', $user->id)->exists())->toBeFalse();
});

it('lands on the onboarding roster when the magic link carries one owned by the user', function () {
    $user = User::factory()->create();
    $roster = Roster::factory()->for($user, 'owner')->create();

    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id, 'roster' => $roster->id]);

    $response = $this->get($url);

    $response->assertRedirect(route('rosters.show', $roster));
    $this->assertAuthenticatedAs($user);
});

it('falls back to the dashboard when the magic link roster is not owned by the user', function () {
    $user = User::factory()->create();
    $otherOwner = User::factory()->create();
    $roster = Roster::factory()->for($otherOwner, 'owner')->create();

    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id, 'roster' => $roster->id]);

    $response = $this->get($url);

    $response->assertRedirect(route('dashboard'));
});

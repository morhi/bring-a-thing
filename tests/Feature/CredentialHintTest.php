<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('F-02: shares the email hint with an account that has no password and no passkey', function () {
    $user = User::factory()->shadow()->create(['email' => 'a@example.com']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('credentialHint', 'email'));
});

it('F-02: shares the no-email variant with a name-only account', function () {
    $user = User::factory()->create(['email' => null, 'password' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('credentialHint', 'no_email'));
});

it('F-02: shares no hint when :dataset', function (string $case) {
    $user = match ($case) {
        'dismissed' => User::factory()->create(['password' => null, 'credential_hint_dismissed_at' => now()]),
        'has password' => User::factory()->create(),
        'has passkey' => tap(User::factory()->create(['password' => null]), fn (User $user) => createPasskeyFor($user)),
        default => null,
    };
    $request = $user ? $this->actingAs($user) : $this;

    $request->get(route($user ? 'dashboard' : 'welcome'))
        ->assertInertia(fn (Assert $page) => $page->where('credentialHint', null));
})->with(['guest', 'dismissed', 'has password', 'has passkey']);

it('F-02: dismissing stores the timestamp and hides the hint', function () {
    $this->freezeTime();
    $user = User::factory()->shadow()->create(['email' => 'a@example.com']);

    $this->actingAs($user)->postJson(route('credential-hint.dismiss'))->assertOk();
    $this->actingAs($user)->postJson(route('credential-hint.dismiss'))->assertOk();

    expect($user->fresh()->credential_hint_dismissed_at->toDateTimeString())->toBe(now()->toDateTimeString());

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('credentialHint', null));
});

it('F-02: a guest cannot dismiss and is sent to login', function () {
    User::factory()->shadow()->create();

    $this->post(route('credential-hint.dismiss'))->assertRedirect(route('login'));

    expect(User::whereNotNull('credential_hint_dismissed_at')->count())->toBe(0);
});

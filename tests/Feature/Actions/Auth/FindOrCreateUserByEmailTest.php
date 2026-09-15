<?php

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Models\User;

it('creates a shadow user with no name or password when the email is unknown', function () {
    $user = (new FindOrCreateUserByEmail)->handle('unknown@example.com');

    expect($user->wasRecentlyCreated)->toBeTrue()
        ->and($user->email)->toBe('unknown@example.com')
        ->and($user->name)->toBeNull()
        ->and($user->password)->toBeNull();
});

it('returns the existing user when the email is already registered', function () {
    $existing = User::factory()->create(['email' => 'known@example.com']);

    $user = (new FindOrCreateUserByEmail)->handle('known@example.com');

    expect($user->is($existing))->toBeTrue()
        ->and(User::query()->where('email', 'known@example.com')->count())->toBe(1);
});

it('backfills a name on an existing user that has none', function () {
    $shadow = User::factory()->shadow()->create(['email' => 'shadow@example.com']);

    $user = (new FindOrCreateUserByEmail)->handle('shadow@example.com', 'Shadow Person');

    expect($user->is($shadow))->toBeTrue()
        ->and($user->fresh()->name)->toBe('Shadow Person');
});

it('never overwrites a name the user already has', function () {
    $existing = User::factory()->create(['email' => 'known@example.com', 'name' => 'Original Name']);

    (new FindOrCreateUserByEmail)->handle('known@example.com', 'Someone Else');

    expect($existing->fresh()->name)->toBe('Original Name');
});

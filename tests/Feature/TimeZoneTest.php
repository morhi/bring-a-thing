<?php

use App\Models\User;

it('F-02: stores the detected browser zone, or Europe/Zurich when it is invalid or missing', function (?string $detected, string $stored) {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)
        ->patch(route('settings.timezone'), array_filter(['timezone' => $detected, 'detected' => true], fn ($value) => $value !== null))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->timezone)->toBe($stored);
})->with([
    'valid zone' => ['America/New_York', 'America/New_York'],
    'invalid zone' => ['Mars/Olympus', 'Europe/Zurich'],
    'missing zone' => [null, 'Europe/Zurich'],
]);

it('F-02: never overwrites an existing zone with a detected one', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)
        ->patch(route('settings.timezone'), ['timezone' => 'America/New_York', 'detected' => true])
        ->assertRedirect();

    expect($user->fresh()->timezone)->toBe('Asia/Tokyo');
});

it('F-02: saves an explicitly chosen zone', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)
        ->patch(route('settings.timezone'), ['timezone' => 'Europe/London'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($user->fresh()->timezone)->toBe('Europe/London');
});

it('F-02: rejects an explicitly chosen invalid zone', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

    $this->actingAs($user)
        ->patch(route('settings.timezone'), ['timezone' => 'Mars/Olympus'])
        ->assertSessionHasErrors('timezone');

    expect($user->fresh()->timezone)->toBe('Asia/Tokyo');
});

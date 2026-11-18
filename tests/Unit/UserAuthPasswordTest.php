<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

it('F-01: getAuthPassword returns a fixed, well-formed, unusable bcrypt string for a null password', function (string $attempt) {
    $first = User::factory()->shadow()->make();
    $second = User::factory()->shadow()->make();
    $fallback = $first->getAuthPassword();

    expect($fallback)->toBe($second->getAuthPassword())
        ->and(Hash::info($fallback)['algoName'])->toBe('bcrypt')
        ->and(Hash::check($attempt === 'fallback' ? $fallback : $attempt, $fallback))->toBeFalse()
        ->and($first->password)->toBeNull();
})->with(['empty' => [''], 'anything' => ['anything'], 'password' => ['password'], 'the fallback itself' => ['fallback']]);

it('F-01: getAuthPassword returns the stored hash for a user with a password', function () {
    $user = User::factory()->make(['password' => 'secret-pass']);

    expect($user->getAuthPassword())->toBe($user->password)
        ->and(Hash::check('secret-pass', $user->getAuthPassword()))->toBeTrue();
});

<?php

use App\Data\SharedReverbData;
use App\Models\User;

test('the browser connection defaults to the server values', function () {
    config([
        'reverb.client.key' => 'app-key',
        'reverb.client.host' => 'bring-a-thing.test',
        'reverb.client.port' => 8443,
        'reverb.client.scheme' => 'https',
    ]);

    expect(SharedReverbData::fromConfig()->toArray())->toBe([
        'key' => 'app-key',
        'host' => 'bring-a-thing.test',
        'port' => 8443,
        'scheme' => 'https',
    ]);
});

test('no connection is shared while Reverb has no key or host', function (?string $key, ?string $host) {
    config(['reverb.client.key' => $key, 'reverb.client.host' => $host]);

    expect(SharedReverbData::fromConfig())->toBeNull();
})->with([
    'no key' => [null, 'bring-a-thing.test'],
    'no host' => ['app-key', null],
]);

test('every Inertia page receives the connection for the browser', function () {
    config([
        'reverb.client.key' => 'app-key',
        'reverb.client.host' => 'bring-a-thing.test',
        'reverb.client.port' => 443,
        'reverb.client.scheme' => 'https',
    ]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('reverb.key', 'app-key')
        ->where('reverb.host', 'bring-a-thing.test')
        ->where('reverb.port', 443)
        ->where('reverb.scheme', 'https'));
});

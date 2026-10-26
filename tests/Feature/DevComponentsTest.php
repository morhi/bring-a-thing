<?php

use Inertia\Testing\AssertableInertia as Assert;

/**
 * Boot a fresh application whose environment is the given one, since routes are registered at boot.
 */
function bootInEnvironment(string $environment): void
{
    foreach (['putenv' => true, '_ENV' => true, '_SERVER' => true] as $target => $unused) {
        if ($target === 'putenv') {
            putenv("APP_ENV={$environment}");

            continue;
        }

        $GLOBALS[$target]['APP_ENV'] = $environment;
    }

    test()->refreshApplication();
}

afterEach(function () {
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
});

it('Dev: renders the components page for guests in the local environment', function () {
    bootInEnvironment('local');

    $this->get(route('dev.components'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dev/Components'));
});

it('Dev: has no components route in production', function () {
    bootInEnvironment('production');

    $this->get('/dev/components')->assertNotFound();
});

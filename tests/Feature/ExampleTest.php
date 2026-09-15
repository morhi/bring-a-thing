<?php

use App\Models\User;

test('guests are redirected from the dashboard to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can view the dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

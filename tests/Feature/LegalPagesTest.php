<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('F-33: serves the privacy policy and legal notice to guests and logged-in accounts', function (bool $loggedIn, string $route, string $component) {
    config()->set('legal', ['name' => 'Erika Muster', 'address' => null, 'email' => null]);

    if ($loggedIn) {
        $this->actingAs(User::factory()->create());
    }

    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'guest privacy' => [false, 'privacy', 'Legal/Privacy'],
    'guest notice' => [false, 'legal-notice', 'Legal/Notice'],
    'account privacy' => [true, 'privacy', 'Legal/Privacy'],
    'account notice' => [true, 'legal-notice', 'Legal/Notice'],
]);

it('F-33: passes the configured operator details to both pages', function (string $route) {
    config()->set('legal', [
        'name' => 'Erika Muster',
        'address' => 'Musterweg 1, 12345 Musterstadt',
        'email' => 'legal@example.com',
    ]);

    $this->get(route($route))
        ->assertInertia(fn (Assert $page) => $page
            ->where('operator.name', 'Erika Muster')
            ->where('operator.address', 'Musterweg 1, 12345 Musterstadt')
            ->where('operator.email', 'legal@example.com'));
})->with(['privacy', 'legal-notice']);

it('F-33: renders both pages with empty operator fields when nothing is configured', function (string $route) {
    config()->set('legal', ['name' => null, 'address' => null, 'email' => null]);

    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('operator.name', null)
            ->where('operator.address', null)
            ->where('operator.email', null));
})->with(['privacy', 'legal-notice']);

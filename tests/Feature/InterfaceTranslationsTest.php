<?php

use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Inertia\Testing\AssertableInertia as Assert;

it('F-24: has the same keys and placeholders in English and German', function () {
    $en = Lang::getLoader()->load('en', 'ui');
    $de = Lang::getLoader()->load('de', 'ui');

    expect($en)->toHaveCount(652)
        ->and(array_diff_key($en, $de))->toBe([])
        ->and(array_diff_key($de, $en))->toBe([]);

    $placeholders = fn (string $text): array => tap(
        preg_match_all('/:[A-Za-z]\w*/', $text, $matches) ? $matches[0] : [],
        sort(...),
    );

    foreach ($en as $key => $text) {
        expect($placeholders($de[$key]))->toBe($placeholders($text), "Placeholders differ for {$key}");
    }
});

it('F-24: shares the active locale strings with the frontend', function () {
    $this->withHeader('Accept-Language', 'de')
        ->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('translations', fn ($strings) => $strings['nav.logout'] === 'Abmelden' && $strings['nav.backTo'] === 'Zurück zu :name'));

    $this->withHeader('Accept-Language', 'en')
        ->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('translations', fn ($strings) => $strings['nav.logout'] === 'Log out' && $strings['nav.backTo'] === 'Back to :name'));
});

it('F-24: switching the language changes the shared strings', function () {
    $user = User::factory()->language('en')->create();

    $this->actingAs($user)
        ->patch(route('language.update'), ['language' => 'de'])
        ->assertRedirect();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'de')
            ->where('translations', fn ($strings) => $strings['nav.logout'] === 'Abmelden'));
});

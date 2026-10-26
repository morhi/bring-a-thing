<?php

use App\Actions\Auth\SendMagicLink;
use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('F-24: uses the browser language on a first visit when it asks for German', function () {
    $this->withHeader('Accept-Language', 'de-CH,de;q=0.9,en;q=0.5')
        ->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));
});

it('F-24: falls back to English when the browser asks for neither English nor German', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('F-24: falls back to English without an Accept-Language header', function () {
    $this->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('F-24: prefers the guest session choice over the browser header', function () {
    $this->withSession(['locale' => 'en'])
        ->withHeader('Accept-Language', 'de')
        ->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

it('F-24: prefers the account language over session and browser', function () {
    $user = User::factory()->language('de')->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->withHeader('Accept-Language', 'en')
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'de')
            ->where('auth.user.language', 'de'));
});

it('F-24: lets a guest switch language for the session without touching accounts', function () {
    $this->patch(route('language.update'), ['language' => 'de'])
        ->assertRedirect()
        ->assertSessionHas('locale', 'de')
        ->assertSessionHas('success');

    $this->get(route('welcome'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));
});

it('F-24: persists the switched language on the account', function () {
    $user = User::factory()->language('en')->create();

    $this->actingAs($user)
        ->patch(route('language.update'), ['language' => 'de'])
        ->assertRedirect();

    expect($user->fresh()->language)->toBe('de');
    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));
});

it('F-24: rejects an unsupported language', function (mixed $language) {
    $user = User::factory()->language('en')->create();

    $this->actingAs($user)
        ->patch(route('language.update'), ['language' => $language])
        ->assertInvalid('language');

    expect($user->fresh()->language)->toBe('en');
})->with(['fr', '', 'EN']);

it('F-24: shares only safe user columns with the frontend', function () {
    $user = User::factory()->language('de')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.user', fn (Assert $u) => $u
                ->where('email', $user->email)
                ->missing('password')
                ->missing('remember_token')
                ->etc())
            ->has('flash'));
});

it('F-24: sends the login email in the account language', function () {
    Mail::fake();
    $user = User::factory()->language('de')->create();

    app(SendMagicLink::class)->handle($user);

    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->locale === 'de'
            && str_contains($mail->render(), 'Anmelden') && trans('Your magic link', [], $mail->locale) === 'Dein Anmeldelink';
    });
});

it('F-01: renders the login email in English for an English account', function () {
    Mail::fake();
    $user = User::factory()->language('en')->create();

    app(SendMagicLink::class)->handle($user);

    Mail::assertQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->locale === 'en' && str_contains($mail->render(), 'Log in'));
});

it('F-01: gives a new account the language of the requesting browser and mails in it', function () {
    Mail::fake();

    $this->withHeader('Accept-Language', 'de')
        ->post(route('magic-link.store'), ['name' => 'Neu', 'email' => 'neu@example.com']);

    expect(User::query()->where('email', 'neu@example.com')->value('language'))->toBe('de');
    Mail::assertQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->locale === 'de');
});

it('F-02: lets the settings page switch the account language', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('language.update'), ['language' => 'de'])->assertRedirect();

    expect($user->fresh()->language)->toBe('de');
});

it('F-24: returns German validation messages for a user whose language is German', function () {
    $user = User::factory()->language('de')->create();

    $this->actingAs($user)
        ->patch(route('settings.profile'), ['name' => ''])
        ->assertInvalid(['name' => 'Name ist erforderlich.']);
});

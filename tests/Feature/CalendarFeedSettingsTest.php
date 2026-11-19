<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('F-34: guests cannot enable, regenerate or disable the calendar feed', function (string $method, string $route) {
    $user = User::factory()->create(['calendar_token' => 'tok']);

    $this->{$method}(route($route))->assertRedirect(route('login'));

    expect($user->fresh()->calendar_token)->toBe('tok');
})->with([
    'enable' => ['post', 'settings.calendar.store'],
    'regenerate' => ['put', 'settings.calendar.update'],
    'disable' => ['delete', 'settings.calendar.destroy'],
]);

it('F-34: enabling the calendar feed generates a token and flashes success', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('settings.calendar.store'))
        ->assertRedirect()
        ->assertSessionHas('success', __('ui.settings.calendar.enabled'));

    $token = $user->fresh()->calendar_token;
    expect($token)->not->toBeNull()->toHaveLength(32)
        ->and(route('calendar.feed', $token))->toEndWith("/calendar/{$token}.ics");
});

it('F-34: enabling again keeps the existing token', function () {
    $user = User::factory()->create();
    $token = $user->enableCalendarFeed();

    $this->actingAs($user)->post(route('settings.calendar.store'))
        ->assertSessionHas('success', __('ui.settings.calendar.enabled'));

    expect($user->fresh()->calendar_token)->toBe($token);
});

it('F-34: regenerating replaces the token and the old one no longer belongs to the user', function (bool $enabled) {
    $user = User::factory()->create();
    $old = $enabled ? $user->enableCalendarFeed() : null;

    $this->actingAs($user)->put(route('settings.calendar.update'))
        ->assertSessionHas('success', __('ui.settings.calendar.regenerated'));

    $new = $user->fresh()->calendar_token;
    expect($new)->not->toBeNull()->toHaveLength(32)->not->toBe($old);

    if ($old !== null) {
        expect(User::where('calendar_token', $old)->exists())->toBeFalse();
        $this->get(route('calendar.feed', $old))->assertNotFound();
    }
})->with([
    'enabled before' => [true],
    'disabled before' => [false],
]);

it('F-34: disabling clears the token and the feed url', function () {
    $user = User::factory()->create();
    $token = $user->enableCalendarFeed();

    $this->actingAs($user)->delete(route('settings.calendar.destroy'))
        ->assertSessionHas('success', __('ui.settings.calendar.disabled'));

    expect($user->fresh()->calendar_token)->toBeNull()
        ->and($user->fresh()->calendarFeedUrl())->toBeNull()
        ->and(User::where('calendar_token', $token)->exists())->toBeFalse();
    $this->get(route('calendar.feed', $token))->assertNotFound();
});

it('F-34: disabling when the feed is already off stays a no-op with success flash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('settings.calendar.destroy'))
        ->assertRedirect()
        ->assertSessionHas('success', __('ui.settings.calendar.disabled'));

    expect($user->fresh()->calendar_token)->toBeNull();
});

it('F-34: calendar actions only change the acting user\'s token', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $a->enableCalendarFeed();
    $bToken = $b->enableCalendarFeed();
    $aToken = $a->calendar_token;

    expect($aToken)->not->toBe($bToken);

    $this->actingAs($a)->put(route('settings.calendar.update'));
    expect($a->fresh()->calendar_token)->not->toBe($aToken)->not->toBeNull()
        ->and($b->fresh()->calendar_token)->toBe($bToken);

    $this->actingAs($a)->delete(route('settings.calendar.destroy'));
    expect($a->fresh()->calendar_token)->toBeNull()
        ->and($b->fresh()->calendar_token)->toBe($bToken);
});

it('F-34: calendarFeedUrl is null without token and the absolute feed url with token', function () {
    $user = User::factory()->create();

    expect($user->calendarFeedUrl())->toBeNull();

    $user->enableCalendarFeed();

    expect($user->calendarFeedUrl())->toBe(route('calendar.feed', $user->calendar_token));
});

it('F-34: the calendar token is not serialized', function () {
    $user = User::factory()->create();
    $user->enableCalendarFeed();

    expect(array_key_exists('calendar_token', $user->toArray()))->toBeFalse();

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.calendar_token')
            ->missing('settings.calendar_token')
            ->where('settings.calendar_feed_url', $user->calendarFeedUrl()));
});

it('F-34: the public feed serves an empty calendar for a valid token', function () {
    $user = User::factory()->create();
    $token = $user->enableCalendarFeed();

    $this->get(route('calendar.feed', $token))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
        ->assertHeader('Content-Disposition', 'inline; filename="bring-a-thing.ics"')
        ->assertSee('BEGIN:VCALENDAR', false);
});

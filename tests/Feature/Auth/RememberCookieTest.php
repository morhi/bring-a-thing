<?php

use App\Enums\GroupRole;
use App\Http\Middleware\EnsureRecentLogin;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('F-01: R-1 a login link logs the user back in from the remember cookie after the session is gone', function (bool $passwordless) {
    $user = $passwordless ? User::factory()->shadow()->create() : User::factory()->create();

    $recaller = loginViaLink($user);
    dropSession();

    assertRestoredFrom($recaller, $user, route('dashboard'));
})->with(['passwordless' => [true], 'with password' => [false]]);

it('F-26: R-2 a name-only guest of an event invite link is restored by the remember cookie', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $token = $event->enableInviteLink();

    $recaller = recallerFrom($this->post(route('events.join.store', $token), ['name' => 'Name Only']));
    $guest = User::query()->where('name', 'Name Only')->firstOrFail();
    dropSession();

    assertRestoredFrom($recaller, $guest, route('events.show', $event));
    expect($event->grantsAccessTo($guest))->toBeTrue();
});

it('F-01: R-3 password login without a remember field is restored by the remember cookie', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $recaller = recallerFrom($this->post(route('login.password'), ['email' => $user->email, 'password' => 'correct-password']));
    dropSession();

    assertRestoredFrom($recaller, $user, route('dashboard'));
});

it('F-02: R-5 setting or changing the password keeps the current device restorable', function (bool $hasPassword) {
    if ($hasPassword) {
        $user = User::factory()->create(['password' => 'old-password']);
        $this->post(route('login.password'), ['email' => $user->email, 'password' => 'old-password']);
    } else {
        $user = User::factory()->shadow()->create();
        loginViaLink($user);
    }

    $response = $this->patch(route('settings.password'), [
        ...($hasPassword ? ['current_password' => 'old-password'] : []),
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ]);
    $response->assertSessionHas('success', __($hasPassword ? 'ui.settings.password.changeDone' : 'ui.settings.password.setDone'));
    $recaller = recallerFrom($response);
    dropSession();

    assertRestoredFrom($recaller, $user, route('dashboard'));
})->with(['set from null' => [false], 'change' => [true]]);

it('F-02: R-6 setting the password does not start the 15 minute passkey window', function () {
    $user = User::factory()->shadow()->create();
    $stale = now()->subMinutes(16)->timestamp;

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => $stale])
        ->patch(route('settings.password'), ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password'])
        ->assertSessionHasNoErrors();

    expect(session(EnsureRecentLogin::SESSION_KEY))->toBe($stale);
    $this->getJson('/user/passkeys/options')->assertForbidden();
});

it('F-02: a password change keeps a pending group membership pending and still re-issues the remember cookie', function () {
    $user = User::factory()->shadow()->create();
    $group = Group::factory()->create();
    $group->addMember($user, GroupRole::Member, null);

    $response = $this->actingAs($user)
        ->patch(route('settings.password'), ['password' => 'a-new-password', 'password_confirmation' => 'a-new-password']);

    recallerFrom($response);
    expect(GroupMember::query()->where('user_id', $user->id)->whereNull('accepted_at')->count())->toBe(1);
});

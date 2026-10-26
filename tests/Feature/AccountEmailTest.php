<?php

use App\Enums\GroupRole;
use App\Mail\MagicLinkMail;
use App\Models\Friend;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * Request an email for a name-only account and return the queued confirmation link.
 */
function requestEmailConfirmation(User $user, string $email): string
{
    test()->actingAs($user)->patch(route('settings.email'), ['email' => $email])->assertSessionHasNoErrors();

    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url, $email) {
        if ($mail->hasTo($email)) {
            $url = $mail->url;
        }

        return true;
    });

    return $url;
}

it('F-01: a name-only joiner who adds an email can log in on another device through the login link', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $group = Group::factory()->withInviteLink()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Link Joiner']);
    $joiner = User::query()->where('name', 'Link Joiner')->firstOrFail();

    $this->patch(route('settings.email'), ['email' => 'joiner@example.com'])
        ->assertRedirect()
        ->assertSessionHas('success', 'A confirmation link has been sent to your email address.');
    $confirmUrl = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$confirmUrl) {
        $confirmUrl = $mail->url;

        return $mail->hasTo('joiner@example.com');
    });
    $this->get($confirmUrl)->assertRedirect(route('settings.edit'));
    expect($joiner->fresh()->email)->toBe('joiner@example.com');

    Auth::logout();
    $this->flushSession();
    Mail::fake();
    $this->post(route('magic-link.store'), ['email' => 'joiner@example.com'])->assertSessionHasNoErrors();
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $this->get($url)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($joiner);
});

it('F-04: an added email is not set and not usable for login before confirmation', function () {
    Mail::fake();
    $attacker = User::factory()->create(['email' => null, 'email_verified_at' => null]);

    requestEmailConfirmation($attacker, 'victim@example.com');

    $attacker->refresh();
    expect($attacker->email)->toBeNull();
    expect($attacker->pending_email)->toBe('victim@example.com');

    Auth::logout();
    $this->flushSession();
    $this->post(route('magic-link.store'), ['email' => 'victim@example.com', 'name' => 'Victim']);
    $victim = User::query()->where('email', 'victim@example.com')->firstOrFail();
    expect($victim->isNot($attacker))->toBeTrue();
});

it('F-04: confirming the link sets the email for the owner of the account', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $url = requestEmailConfirmation($user, 'mine@example.com');

    $this->actingAs($user)->get($url)
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('success', 'Email confirmed. You can now log in with it on other devices.');

    $user->refresh();
    expect($user->email)->toBe('mine@example.com');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->pending_email)->toBeNull();
});

it('F-04: rejects an expired confirmation link', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $url = requestEmailConfirmation($user, 'late@example.com');

    $this->travel(31)->minutes();

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->fresh()->email)->toBeNull();
});

it('F-04: rejects a confirmation link opened by another account', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $other = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $url = requestEmailConfirmation($user, 'owner@example.com');

    $this->actingAs($other)->get($url)->assertForbidden();

    expect($user->fresh()->email)->toBeNull();
    expect($other->fresh()->email)->toBeNull();
});

it('F-04: rejects a replayed confirmation link', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $url = requestEmailConfirmation($user, 'once@example.com');
    $this->actingAs($user)->get($url)->assertRedirect(route('settings.edit'));

    $this->actingAs($user)->get($url)->assertForbidden();
});

it('F-04: a new pending email replaces the old one and voids its link', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $firstUrl = requestEmailConfirmation($user, 'first@example.com');
    requestEmailConfirmation($user, 'second@example.com');

    expect($user->fresh()->pending_email)->toBe('second@example.com');
    $this->actingAs($user)->get($firstUrl)->assertForbidden();
    expect($user->fresh()->email)->toBeNull();
});

it('F-04: rejects confirming an address another account took meanwhile', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
    $url = requestEmailConfirmation($user, 'contested@example.com');
    User::factory()->create(['email' => 'contested@example.com']);

    $this->actingAs($user)->get($url)
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors(['email' => __('ui.settings.email.taken')]);

    $user->refresh();
    expect($user->email)->toBeNull();
    expect($user->pending_email)->toBeNull();
});

it('F-04: a closed invite link rejects a logged-in account with an unverified email', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->withInviteLink(true)->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($unverified)->post(route('groups.join.store', $group->invite_token))->assertInvalid('email');

    expect($group->hasMember($unverified))->toBeFalse();
});

it('F-04: a closed invite link accepts a logged-in account with a verified email', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->withInviteLink(true)->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $verified = User::factory()->create();

    $this->actingAs($verified)->post(route('groups.join.store', $group->invite_token))->assertRedirect(route('groups.show', $group));

    expect($group->hasMember($verified))->toBeTrue();
});

it('F-01: logging in through a magic link verifies the email', function () {
    Mail::fake();
    $user = User::factory()->unverified()->create(['email' => 'fresh@example.com']);
    $this->post(route('magic-link.store'), ['email' => 'fresh@example.com']);
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $this->get($url);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('F-01: rejects adding an email that another account already uses', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $nameOnly = User::factory()->create(['email' => null]);

    $this->actingAs($nameOnly)->patch(route('settings.email'), ['email' => 'taken@example.com'])->assertInvalid('email');

    expect($nameOnly->fresh()->email)->toBeNull();
});

it('F-01: offers adding an email only to accounts without one', function () {
    $user = User::factory()->create(['email' => 'has@example.com']);

    $this->actingAs($user)->patch(route('settings.email'), ['email' => 'other@example.com'])->assertForbidden();

    expect($user->fresh()->email)->toBe('has@example.com');
});

it('F-05: after an invitation, the person appears on the "Friends" page', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $this->actingAs($owner)->post(route('groups.invite', $group), ['name' => 'Cleo Invitee', 'email' => 'cleo@example.com']);

    $this->actingAs($owner)
        ->get(route('friends.index'))
        ->assertInertia(fn ($page) => $page
            ->where('friends.0.name', 'Cleo Invitee')
            ->where('friends.0.friend_user.email', 'cleo@example.com'));
});

it('F-05: a removed friend stays a member of the groups they were in', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $this->actingAs($owner)->post(route('groups.invite', $group), ['name' => 'Dana Invitee', 'email' => 'dana@example.com']);
    $friend = Friend::query()->where('user_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)->delete(route('friends.destroy', $friend))->assertRedirect();

    $dana = User::query()->where('email', 'dana@example.com')->firstOrFail();
    $this->assertModelMissing($friend);
    expect($group->hasMember($dana))->toBeTrue();
});

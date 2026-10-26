<?php

use App\Enums\GroupRole;
use App\Mail\MagicLinkMail;
use App\Models\Friend;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

it('F-01: a name-only joiner who adds an email can log in on another device through the login link', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $group = Group::factory()->withInviteLink()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Link Joiner']);
    $joiner = User::query()->where('name', 'Link Joiner')->firstOrFail();

    $this->patch(route('settings.email'), ['email' => 'joiner@example.com'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Email added. A login link has been sent to it.');
    expect($joiner->fresh()->email)->toBe('joiner@example.com');
    Mail::assertQueued(MagicLinkMail::class, fn (MagicLinkMail $mail) => $mail->hasTo('joiner@example.com'));

    Auth::logout();
    $this->flushSession();
    $this->post(route('magic-link.store'), ['email' => 'joiner@example.com'])->assertSessionHasNoErrors();
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $this->get($url)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($joiner);
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

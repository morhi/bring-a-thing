<?php

use App\Enums\GroupJoinMethod;
use App\Enums\GroupRole;
use App\Mail\MagicLinkMail;
use App\Models\Friend;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with its owner and an optional invite link.
 *
 * @return array{0: Group, 1: User}
 */
function groupWithOwner(?bool $locked = null): array
{
    $owner = User::factory()->create();
    $factory = Group::factory();

    if ($locked !== null) {
        $factory = $factory->withInviteLink($locked);
    }

    $group = $factory->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    return [$group, $owner];
}

it('F-04: creates the invite link on first use and keeps it on later copies', function () {
    [$group, $owner] = groupWithOwner();

    $this->actingAs($owner)
        ->post(route('groups.invite-link.store', $group))
        ->assertRedirect()
        ->assertSessionHas('success', 'Invite link created.');
    $token = $group->fresh()->invite_token;

    $this->actingAs($owner)->post(route('groups.invite-link.store', $group));

    expect($token)->not->toBeNull();
    expect($group->fresh()->invite_token)->toBe($token);
    expect($group->fresh()->invite_link_locked)->toBeFalse();
});

it('F-04: lets an admin create the invite link', function () {
    [$group] = groupWithOwner();
    $admin = User::factory()->create();
    $group->addMember($admin, GroupRole::Admin, now());

    $this->actingAs($admin)->post(route('groups.invite-link.store', $group))->assertRedirect();

    expect($group->fresh()->invite_token)->not->toBeNull();
});

it('F-04: a member without the admin role sees no invite link button', function () {
    [$group] = groupWithOwner(locked: false);
    $member = User::factory()->create();
    $group->addMember($member, GroupRole::Member, now());

    $this->actingAs($member)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('inviteLink', null));
    $this->actingAs($member)->post(route('groups.invite-link.store', $group))->assertForbidden();
    $this->actingAs($member)->post(route('groups.invite-link.regenerate', $group))->assertForbidden();
    $this->actingAs($member)->delete(route('groups.invite-link.destroy', $group))->assertForbidden();
});

it('F-04: shows the invite link to owners without exposing the token in the group', function () {
    [$group, $owner] = groupWithOwner(locked: false);

    $this->actingAs($owner)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inviteLink.url', route('groups.join.show', $group->invite_token))
            ->where('inviteLink.locked', false)
            ->missing('group.invite_token'));
});

it('F-04: a member without the admin role sees no invite form', function () {
    [$group] = groupWithOwner();
    $member = User::factory()->create();
    $group->addMember($member, GroupRole::Member, now());

    $this->actingAs($member)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('canManageMembers', false));
    $this->actingAs($member)
        ->post(route('groups.invite', $group), ['name' => 'Jane', 'email' => 'jane@example.com'])
        ->assertForbidden();
});

it('F-04: with an open link, a person who enters a name appears in the member list at once', function () {
    [$group] = groupWithOwner(locked: false);

    $response = $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Link Joiner']);

    $joiner = User::query()->where('name', 'Link Joiner')->firstOrFail();
    $response->assertRedirect(route('groups.show', $group));
    $response->assertSessionHas('success', 'You joined the group.');
    $this->assertAuthenticatedAs($joiner);
    expect($joiner->email)->toBeNull();

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.members.1.name', 'Link Joiner')
            ->where('group.members.1.pivot.role', 'member')
            ->where('group.members.1.pivot.joined_via', 'link')
            ->whereNot('group.members.1.pivot.accepted_at', null));
});

it('F-01: keeps a name-only joiner logged in on that browser', function () {
    [$group] = groupWithOwner(locked: false);

    $response = $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Link Joiner']);

    $joiner = User::query()->where('name', 'Link Joiner')->firstOrFail();
    expect($joiner->remember_token)->not->toBeNull();
    $response->assertCookie(Auth::guard()->getRecallerName());
});

it('F-04: requires a name to join through an open link', function () {
    [$group] = groupWithOwner(locked: false);

    $this->post(route('groups.join.store', $group->invite_token), ['name' => ''])->assertInvalid('name');

    expect($group->members()->count())->toBe(1);
    $this->assertGuest();
});

it('F-04: shows the join page for a valid link', function () {
    $this->withoutVite();
    [$group] = groupWithOwner(locked: true);

    $this->get(route('groups.join.show', $group->invite_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Join', false)
            ->where('join.group_name', $group->name)
            ->where('join.locked', true)
            ->where('join.needs_email', true)
            ->where('join.is_authenticated', false));
});

it('F-04: with a closed link, a person appears as a member only after logging in with an email', function () {
    Mail::fake();
    [$group] = groupWithOwner(locked: true);

    $response = $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Closed Joiner', 'email' => 'closed@example.com']);

    $response->assertSessionHas('success', 'A login link has been sent to your email address.');
    $joiner = User::query()->where('email', 'closed@example.com')->firstOrFail();
    expect($group->hasMember($joiner))->toBeFalse();
    $this->assertGuest();

    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return $mail->hasTo('closed@example.com');
    });

    $this->get($url)->assertRedirect(route('groups.show', $group));

    $this->assertAuthenticatedAs($joiner);
    $pivot = $group->members()->whereKey($joiner->id)->first()?->pivot;
    expect($pivot->joined_via)->toBe(GroupJoinMethod::Link);
    expect($pivot->accepted_at)->not->toBeNull();
});

it('F-04: rejects a name-only join through a closed link', function () {
    [$group] = groupWithOwner(locked: true);

    $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Name Only'])->assertInvalid('email');

    expect(User::query()->where('name', 'Name Only')->exists())->toBeFalse();
});

it('F-04: does not join through a login link requested before the invite link was revoked', function () {
    Mail::fake();
    [$group, $owner] = groupWithOwner(locked: true);
    $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Late Joiner', 'email' => 'late@example.com']);
    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    $this->actingAs($owner)->delete(route('groups.invite-link.destroy', $group));
    Auth::logout();

    $this->get($url)->assertRedirect(route('dashboard'));

    expect($group->hasMember(User::query()->where('email', 'late@example.com')->firstOrFail()))->toBeFalse();
});

it('F-04: after revoking, the old link shows "Not found"', function () {
    [$group, $owner] = groupWithOwner(locked: false);
    $oldToken = $group->invite_token;

    $this->actingAs($owner)
        ->delete(route('groups.invite-link.destroy', $group))
        ->assertSessionHas('success', 'Invite link revoked.');
    Auth::logout();

    expect($group->fresh()->invite_token)->toBeNull();
    $this->get(route('groups.join.show', $oldToken))->assertNotFound();
    $this->post(route('groups.join.store', $oldToken), ['name' => 'Too Late'])->assertNotFound();
});

it('F-04: after regenerating, the old link shows "Not found" and the new one works', function () {
    $this->withoutVite();
    [$group, $owner] = groupWithOwner(locked: false);
    $oldToken = $group->invite_token;

    $this->actingAs($owner)
        ->post(route('groups.invite-link.regenerate', $group))
        ->assertSessionHas('success', 'Invite link regenerated. The old link no longer works.');
    Auth::logout();

    $newToken = $group->fresh()->invite_token;
    expect($newToken)->not->toBe($oldToken);
    $this->get(route('groups.join.show', $oldToken))->assertNotFound();
    $this->get(route('groups.join.show', $newToken))->assertOk();
});

it('F-04: lets owners and admins switch the lock setting, but not members', function () {
    [$group, $owner] = groupWithOwner(locked: false);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    $this->actingAs($owner)
        ->patch(route('groups.invite-link.update', $group), ['locked' => true])
        ->assertSessionHas('success', 'Invite link updated.');
    expect($group->fresh()->invite_link_locked)->toBeTrue();

    $this->actingAs($admin)->patch(route('groups.invite-link.update', $group), ['locked' => false]);
    expect($group->fresh()->invite_link_locked)->toBeFalse();

    $this->actingAs($member)->patch(route('groups.invite-link.update', $group), ['locked' => true])->assertForbidden();
    expect($group->fresh()->invite_link_locked)->toBeFalse();
});

it('F-04: lets a logged-in account join at once and sends existing members to the group', function () {
    [$group] = groupWithOwner(locked: true);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.join.store', $group->invite_token))
        ->assertRedirect(route('groups.show', $group));

    expect($group->members()->whereKey($user->id)->first()?->pivot->joined_via)->toBe(GroupJoinMethod::Link);
    $this->actingAs($user)
        ->get(route('groups.join.show', $group->invite_token))
        ->assertRedirect(route('groups.show', $group));
});

it('F-04: asks a logged-in account without an email to add one before joining through a closed link', function () {
    [$group] = groupWithOwner(locked: true);
    $nameOnly = User::factory()->create(['email' => null]);

    $this->actingAs($nameOnly)
        ->post(route('groups.join.store', $group->invite_token))
        ->assertInvalid('email');

    expect($group->hasMember($nameOnly))->toBeFalse();
});

it('F-04: gives a person who joined through the link the same access as any other member', function () {
    [$group] = groupWithOwner(locked: false);
    $this->post(route('groups.join.store', $group->invite_token), ['name' => 'Link Joiner']);
    $joiner = User::query()->where('name', 'Link Joiner')->firstOrFail();

    expect($group->members()->whereKey($joiner->id)->first()?->pivot->role)->toBe(GroupRole::Member);
    expect($joiner->can('view', $group))->toBeTrue();
    expect($joiner->can('invite', $group))->toBeFalse();
});

it('F-04: an invited person appears at once with "Invitation pending", which disappears after their first login', function () {
    Mail::fake();
    [$group, $owner] = groupWithOwner();

    $this->actingAs($owner)->post(route('groups.invite', $group), ['name' => 'Jane Invitee', 'email' => 'jane@example.com']);

    $this->actingAs($owner)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.members.1.name', 'Jane Invitee')
            ->where('group.members.1.pivot.accepted_at', null)
            ->where('group.members.1.pivot.joined_via', 'invite'));

    $invitee = User::query()->where('email', 'jane@example.com')->firstOrFail();
    Auth::login($invitee);

    expect($group->members()->whereKey($invitee->id)->first()?->pivot->accepted_at)->not->toBeNull();
});

it('F-04: offers saved friends with name and email for suggestions in the invite field', function () {
    [$group, $owner] = groupWithOwner();
    Friend::factory()->create(['user_id' => $owner->id, 'name' => 'Annika Friend']);

    $this->actingAs($owner)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('friends.0.name', 'Annika Friend')
            ->has('friends.0.friend_user.email'));
});

it('F-04: inviting a friend by email selects that friend and creates no second invitation', function () {
    Mail::fake();
    [$group, $owner] = groupWithOwner();
    $this->actingAs($owner)->post(route('friends.store'), ['name' => 'Bea Friend', 'email' => 'bea@example.com']);

    $this->actingAs($owner)->post(route('groups.invite', $group), ['name' => 'Bea Friend', 'email' => 'bea@example.com']);
    $this->actingAs($owner)
        ->post(route('groups.invite', $group), ['name' => 'Bea Friend', 'email' => 'bea@example.com'])
        ->assertInvalid('email');

    expect(User::query()->where('email', 'bea@example.com')->count())->toBe(1);
    expect(Friend::query()->where('user_id', $owner->id)->count())->toBe(1);
    expect($group->members()->where('email', 'bea@example.com')->count())->toBe(1);
    Mail::assertQueued(MagicLinkMail::class, 1);
});

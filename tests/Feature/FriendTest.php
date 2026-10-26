<?php

use App\Enums\GroupRole;
use App\Models\Friend;
use App\Models\Group;
use App\Models\User;

it('saves the invitee as a friend of the inviter when inviting a new email', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);

    $this->actingAs($owner)->post(route('groups.invite', $group), [
        'invites' => [['name' => 'Jane Invitee', 'email' => 'invitee@example.com']],
    ])->assertRedirect();

    $invitee = User::query()->where('email', 'invitee@example.com')->firstOrFail();
    expect(Friend::query()->where('user_id', $owner->id)->where('friend_user_id', $invitee->id)->exists())->toBeTrue();
});

it('does not duplicate a friend entry when inviting the same email again', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);
    $otherGroup = Group::factory()->create(['owner_id' => $owner->id]);
    $otherGroup->addMember($owner, GroupRole::Owner);

    $this->actingAs($owner)->post(route('groups.invite', $group), ['invites' => [['name' => 'Jane', 'email' => 'jane@example.com']]]);
    $this->actingAs($owner)->post(route('groups.invite', $otherGroup), ['invites' => [['name' => 'Jane', 'email' => 'jane@example.com']]]);

    expect(Friend::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('adds a friend directly by email, creating a shadow account for them', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post(route('friends.store'), [
        'name' => 'Alex',
        'email' => 'alex@example.com',
    ]);

    $response->assertRedirect();
    $alex = User::query()->where('email', 'alex@example.com')->firstOrFail();
    expect(Friend::query()->where('user_id', $owner->id)->where('friend_user_id', $alex->id)->first()?->name)->toBe('Alex');
});

it('rejects adding your own email as a friend', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post(route('friends.store'), [
        'email' => $owner->email,
    ]);

    $response->assertSessionHasErrors('email');
});

it('lets a user remove their own friend entry', function () {
    $owner = User::factory()->create();
    $friend = Friend::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->delete(route('friends.destroy', $friend));

    $response->assertRedirect();
    expect(Friend::query()->whereKey($friend->id)->exists())->toBeFalse();
});

it('forbids removing another user\'s friend entry', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $friend = Friend::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($owner)->delete(route('friends.destroy', $friend));

    $response->assertForbidden();
    expect(Friend::query()->whereKey($friend->id)->exists())->toBeTrue();
});

it('F-05: rejects adding an email that already belongs to a friend', function () {
    $owner = User::factory()->create();
    $friendUser = User::factory()->create(['name' => 'Bo', 'email' => 'bo@example.com']);
    Friend::factory()->create(['user_id' => $owner->id, 'friend_user_id' => $friendUser->id]);

    $this->actingAs($owner)
        ->post(route('friends.store'), ['email' => 'bo@example.com'])
        ->assertSessionHasErrors(['email' => __('ui.friends.add.exists', ['name' => 'Bo'])]);

    expect(Friend::query()->where('user_id', $owner->id)->count())->toBe(1);
});

it('F-05: flashes the added and removed friend by name', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('friends.store'), ['email' => 'new-friend@example.com'])
        ->assertSessionHas('success', __('ui.friends.add.added', ['name' => 'new-friend@example.com']));

    $friend = Friend::query()->where('user_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('friends.destroy', $friend))
        ->assertSessionHas('success', __('ui.friends.removed', ['name' => 'new-friend@example.com']));
});

it('F-05: lists friends with pending state and shared groups, named friends first', function () {
    $owner = User::factory()->create();
    $named = User::factory()->create(['name' => 'Zoe', 'email' => 'zoe@example.com']);
    $shadow = User::factory()->shadow()->create(['email' => 'shadow@example.com']);
    Friend::factory()->create(['user_id' => $owner->id, 'friend_user_id' => $shadow->id]);
    Friend::factory()->create(['user_id' => $owner->id, 'friend_user_id' => $named->id]);
    $group = Group::factory()->create(['owner_id' => $owner->id, 'name' => 'Band']);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($named, GroupRole::Member, now());

    $this->actingAs($owner)
        ->get(route('friends.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Friends')
            ->where('friends.items.0.name', 'Zoe')
            ->where('friends.items.0.shared_groups', ['Band'])
            ->where('friends.items.0.invitation_pending', false)
            ->where('friends.items.1.name', null)
            ->where('friends.items.1.email', 'shadow@example.com')
            ->where('friends.items.1.shared_groups', [])
            ->where('friends.items.1.invitation_pending', true));
});

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
        'name' => 'Jane Invitee',
        'email' => 'invitee@example.com',
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

    $this->actingAs($owner)->post(route('groups.invite', $group), ['name' => 'Jane', 'email' => 'jane@example.com']);
    $this->actingAs($owner)->post(route('groups.invite', $otherGroup), ['name' => 'Jane', 'email' => 'jane@example.com']);

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

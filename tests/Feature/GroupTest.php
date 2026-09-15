<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

it('creates a group with the requesting user as owner and member', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('groups.store'), ['name' => 'Family Trip']);

    $group = Group::query()->where('name', 'Family Trip')->firstOrFail();
    $response->assertRedirect(route('groups.show', $group));
    expect($group->owner_id)->toBe($user->id);
    expect($group->members()->whereKey($user->id)->first()?->pivot->role)->toBe(GroupRole::Owner);
});

it('rejects group creation for guests', function () {
    $response = $this->post(route('groups.store'), ['name' => 'Family Trip']);

    $response->assertRedirect(route('login'));
});

it('allows a member to view the group', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);
    $group->addMember($member, GroupRole::Member);

    $response = $this->actingAs($member)->get(route('groups.show', $group));

    $response->assertOk();
});

it('forbids a non-member from viewing the group', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);

    $response = $this->actingAs($outsider)->get(route('groups.show', $group));

    $response->assertForbidden();
});

it('allows the owner to update the group', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);

    $response = $this->actingAs($owner)->patch(route('groups.update', $group), ['name' => 'Renamed']);

    $response->assertRedirect(route('groups.edit', $group));
    expect($group->fresh()->name)->toBe('Renamed');
});

it('forbids a member from updating the group', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);
    $group->addMember($member, GroupRole::Member);

    $response = $this->actingAs($member)->patch(route('groups.update', $group), ['name' => 'Renamed']);

    $response->assertForbidden();
});

it('allows the owner to delete the group', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);

    $response = $this->actingAs($owner)->delete(route('groups.destroy', $group));

    $response->assertRedirect(route('dashboard'));
    $this->assertModelMissing($group);
});

it('forbids a member from deleting the group', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);
    $group->addMember($member, GroupRole::Member);

    $response = $this->actingAs($member)->delete(route('groups.destroy', $group));

    $response->assertForbidden();
    $this->assertModelExists($group);
});

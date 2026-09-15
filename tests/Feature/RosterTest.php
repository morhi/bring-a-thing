<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;

it('creates a standalone roster owned by the requesting user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('rosters.store'), ['title' => 'Camping Trip']);

    $roster = Roster::query()->where('title', 'Camping Trip')->firstOrFail();
    $response->assertRedirect(route('rosters.show', $roster));
    $response->assertSessionHas('success', 'Roster created.');
    expect($roster->owner_id)->toBe($user->id);
    expect($roster->group_id)->toBeNull();
});

it('creates a roster attached to a group the user belongs to', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $response = $this->actingAs($owner)->post(route('rosters.store'), [
        'title' => 'Meal Plan',
        'group_id' => $group->id,
    ]);

    $roster = Roster::query()->where('title', 'Meal Plan')->firstOrFail();
    $response->assertRedirect(route('rosters.show', $roster));
    expect($roster->group_id)->toBe($group->id);
});

it('forbids attaching a roster to a group the user does not belong to', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $response = $this->actingAs($outsider)->post(route('rosters.store'), [
        'title' => 'Meal Plan',
        'group_id' => $group->id,
    ]);

    $response->assertForbidden();
});

it('allows the owner to view a standalone roster', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('rosters.show', $roster));

    $response->assertOk();
});

it('forbids a non-owner from viewing a standalone roster', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($outsider)->get(route('rosters.show', $roster));

    $response->assertForbidden();
});

it('allows a group member to view a group-attached roster', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $response = $this->actingAs($member)->get(route('rosters.show', $roster));

    $response->assertOk();
});

it('forbids a non-member from viewing a group-attached roster', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $response = $this->actingAs($outsider)->get(route('rosters.show', $roster));

    $response->assertForbidden();
});

it('allows the owner to update the roster', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)->patch(route('rosters.update', $roster), ['title' => 'Renamed']);

    $response->assertRedirect(route('rosters.edit', $roster));
    $response->assertSessionHas('success', 'Roster updated.');
    expect($roster->fresh()->title)->toBe('Renamed');
});

it('forbids a group member from updating a roster they do not own', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $response = $this->actingAs($member)->patch(route('rosters.update', $roster), ['title' => 'Renamed']);

    $response->assertForbidden();
});

it('allows the owner to delete the roster', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)->delete(route('rosters.destroy', $roster));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('success', 'Roster deleted.');
    $this->assertModelMissing($roster);
});

it('forbids a non-owner from deleting the roster', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($outsider)->delete(route('rosters.destroy', $roster));

    $response->assertForbidden();
    $this->assertModelExists($roster);
});

it('deletes attached rosters when their group is deleted', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $group->delete();

    $this->assertModelMissing($roster);
});

it('allows the owner to duplicate the roster with a cleared date', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create([
        'owner_id' => $owner->id,
        'title' => 'Meal Plan',
        'date' => '2026-01-01',
    ]);

    $response = $this->actingAs($owner)->post(route('rosters.duplicate', $roster));

    $copy = Roster::query()->where('title', 'Meal Plan (copy)')->firstOrFail();
    $response->assertRedirect(route('rosters.show', $copy));
    $response->assertSessionHas('success', 'Roster duplicated.');
    expect($copy->owner_id)->toBe($owner->id);
    expect($copy->date)->toBeNull();
});

it('forbids a non-owner from duplicating the roster', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($outsider)->post(route('rosters.duplicate', $roster));

    $response->assertForbidden();
});

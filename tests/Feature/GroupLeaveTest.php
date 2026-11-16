<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('F-03: a member leaves the group, lands on the dashboard with a message and loses access', function (GroupRole $role) {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, $role, now());

    $this->actingAs($member)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_leave', true));

    $this->actingAs($member)
        ->delete(route('groups.leave', $group))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', __('ui.group.left', ['group' => $group->name]));

    expect($group->members()->whereKey($member->id)->exists())->toBeFalse()
        ->and($group->members()->whereKey($owner->id)->exists())->toBeTrue();
    $this->actingAs($member)->get(route('groups.show', $group))->assertNotFound();
})->with([GroupRole::Member, GroupRole::Admin]);

it('F-03: the owner cannot leave the group', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $this->actingAs($owner)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_leave', false));

    $this->actingAs($owner)->delete(route('groups.leave', $group))->assertForbidden();

    expect($group->members()->whereKey($owner->id)->exists())->toBeTrue();
});

it('F-03: a non-member cannot leave a group', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $this->actingAs($guest)->delete(route('groups.leave', $group))->assertForbidden();
});

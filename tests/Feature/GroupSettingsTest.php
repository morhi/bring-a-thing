<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner, an admin and a plain member.
 *
 * @return array{0: Group, 1: User, 2: User, 3: User}
 */
function groupWithRoles(): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $admin, $member];
}

it('F-03: an admin sees no action to delete the group or change roles, and cannot switch the group option', function () {
    [$group, , $admin, $member] = groupWithRoles();

    $this->actingAs($admin)
        ->get(route('groups.edit', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_delete', false)
            ->where('page.permissions.can_change_roles', false)
            ->where('page.permissions.can_switch_option', false)
            ->where('page.permissions.can_rename', false)
            ->has('page.invite_link'));

    $this->actingAs($admin)->patch(route('groups.update', $group), ['members_can_set_status' => true])->assertForbidden();
    $this->actingAs($admin)->patch(route('groups.update', $group), ['name' => 'Renamed'])->assertForbidden();
    $this->actingAs($admin)->patch(route('groups.members.update', [$group, $member]), ['role' => 'admin'])->assertForbidden();
    $this->actingAs($admin)->delete(route('groups.destroy', $group))->assertForbidden();

    expect($group->fresh()->members_can_set_status)->toBeFalse();
    expect($group->fresh()->name)->toBe($group->name);
});

it('F-03: shows the owner every settings action', function () {
    [$group, $owner] = groupWithRoles();

    $this->actingAs($owner)
        ->get(route('groups.edit', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_delete', true)
            ->where('page.permissions.can_change_roles', true)
            ->where('page.permissions.can_switch_option', true)
            ->where('page.permissions.can_rename', true)
            ->where('page.members_can_set_status', false)
            ->has('page.members', 3)
            ->where('page.members.0.role', 'owner'));
});

it('F-03: lets only the owner switch the option "Members can set the participation status for others", off by default', function () {
    [$group, $owner] = groupWithRoles();
    expect($group->fresh()->members_can_set_status)->toBeFalse();

    $this->actingAs($owner)
        ->patch(route('groups.update', $group), ['members_can_set_status' => true])
        ->assertRedirect(route('groups.edit', $group))
        ->assertSessionHas('success', 'Changes saved');

    expect($group->fresh()->members_can_set_status)->toBeTrue();
    expect($group->fresh()->name)->toBe($group->name);
});

it('F-03: shows "Not found" for the group settings to a member without the admin role', function () {
    [$group, , , $member] = groupWithRoles();

    $this->actingAs($member)->get(route('groups.edit', $group))->assertNotFound();
});

it('F-03: makes the group lists unreachable after deleting the group', function () {
    [$group, $owner] = groupWithRoles();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $this->actingAs($owner)->delete(route('groups.destroy', $group))->assertRedirect(route('dashboard'));

    $this->assertModelMissing($roster);
    $this->actingAs($owner)->get(route('rosters.show', $roster->slug))->assertNotFound();
});

it('F-03: shows a clear error when the owner saves an empty group name', function () {
    [$group, $owner] = groupWithRoles();

    $this->actingAs($owner)
        ->patch(route('groups.update', $group), ['name' => ''])
        ->assertInvalid(['name' => 'Give the group a name.']);

    expect($group->fresh()->name)->toBe($group->name);
});

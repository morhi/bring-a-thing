<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\URL;

it('marks a pending invite as accepted the first time the invitee logs in', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->shadow()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($invitee, GroupRole::Member);

    expect($group->members()->whereKey($invitee->id)->first()->pivot->accepted_at)->toBeNull();

    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $invitee->id]);
    $this->get($url);

    expect($group->members()->whereKey($invitee->id)->first()->pivot->accepted_at)->not->toBeNull();
});

it('does not disturb an already-accepted membership on a later login', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $owner->id]);
    $this->get($url);

    expect($group->members()->whereKey($owner->id)->first()->pivot->accepted_at)->not->toBeNull();
});

it('allows the owner to remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    $response = $this->actingAs($owner)->delete(route('groups.members.destroy', [$group, $member]));

    $response->assertRedirect();
    expect($group->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('forbids a member from removing another member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $group->addMember($other, GroupRole::Member, now());

    $response = $this->actingAs($member)->delete(route('groups.members.destroy', [$group, $other]));

    $response->assertForbidden();
    expect($group->members()->whereKey($other->id)->exists())->toBeTrue();
});

it('forbids removing the owner', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $response = $this->actingAs($owner)->delete(route('groups.members.destroy', [$group, $owner]));

    $response->assertForbidden();
    expect($group->members()->whereKey($owner->id)->exists())->toBeTrue();
});

it('allows the owner to promote a member to admin', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    $response = $this->actingAs($owner)->patch(route('groups.members.update', [$group, $member]), ['role' => 'admin']);

    $response->assertRedirect();
    expect($group->members()->whereKey($member->id)->first()->pivot->role)->toBe(GroupRole::Admin);
});

it('forbids a non-owner from changing a member role', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    $response = $this->actingAs($admin)->patch(route('groups.members.update', [$group, $member]), ['role' => 'admin']);

    $response->assertForbidden();
    expect($group->members()->whereKey($member->id)->first()->pivot->role)->toBe(GroupRole::Member);
});

it('forbids changing the owner role', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $response = $this->actingAs($owner)->patch(route('groups.members.update', [$group, $owner]), ['role' => 'admin']);

    $response->assertForbidden();
});

it('allows an admin to invite and remove members, but not delete the group', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    $this->actingAs($admin)
        ->delete(route('groups.members.destroy', [$group, $member]))
        ->assertRedirect();
    expect($group->members()->whereKey($member->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('groups.destroy', $group))
        ->assertForbidden();
});

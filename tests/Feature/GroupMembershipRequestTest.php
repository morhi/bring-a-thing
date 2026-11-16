<?php

use App\Enums\GroupRole;
use App\Enums\NotificationKind;
use App\Models\Group;
use App\Models\GroupMembershipRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner, an admin and a guest who reaches it through a grant.
 *
 * @return array{group: Group, owner: User, admin: User, guest: User}
 */
function requestGroup(): array
{
    [$group, $owner] = dashboardGroup();
    $admin = User::factory()->create();
    $group->addMember($admin, GroupRole::Admin, now());
    $guest = User::factory()->create(['name' => 'Gina Guest']);
    grantOn(dashboardEvent($group, $owner, null, null), $guest);

    return compact('group', 'owner', 'admin', 'guest');
}

it('F-03: a guest requests membership, the owner is notified and the button turns into Request sent', function () {
    ['group' => $group, 'owner' => $owner, 'guest' => $guest] = requestGroup();

    $this->actingAs($guest)->get(route('groups.show', $group))->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_request_membership', true)
        ->where('page.permissions.has_pending_membership_request', false));

    $this->actingAs($guest)
        ->post(route('groups.membership-request.store', $group))
        ->assertRedirect()
        ->assertSessionHas('success', __('ui.group.requestMembership.done', ['group' => $group->name]));

    expect(GroupMembershipRequest::query()->whereBelongsTo($group)->whereBelongsTo($guest)->count())->toBe(1);
    $notification = $owner->fresh()->notifications()->sole();
    expect($notification->data['kind'])->toBe(NotificationKind::GroupMembershipRequested->value)
        ->and($notification->data['actor_id'])->toBe($guest->id);

    $this->actingAs($guest)->get(route('groups.show', $group))->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_request_membership', false)
        ->where('page.permissions.has_pending_membership_request', true));
});

it('F-03: a duplicate, member or outsider request is forbidden and stores nothing', function () {
    ['group' => $group, 'admin' => $admin, 'owner' => $owner, 'guest' => $guest] = requestGroup();
    GroupMembershipRequest::factory()->for($group)->for($guest)->create();
    $outsider = User::factory()->create();

    foreach ([$guest, $admin, $owner, $outsider] as $user) {
        $this->actingAs($user)->post(route('groups.membership-request.store', $group))->assertForbidden();
    }

    expect(GroupMembershipRequest::query()->count())->toBe(1)
        ->and($owner->fresh()->notifications()->count())->toBe(0);
});

it('F-03: the owner accepts a request, the guest becomes a member and the request is gone', function () {
    ['group' => $group, 'owner' => $owner, 'guest' => $guest] = requestGroup();
    GroupMembershipRequest::factory()->for($group)->for($guest)->create();

    $this->actingAs($owner)->get(route('groups.edit', $group))->assertInertia(fn (Assert $page) => $page
        ->has('page.membership_requests', 1)
        ->where('page.membership_requests.0.id', $guest->id));

    $this->actingAs($owner)
        ->post(route('groups.membership-requests.accept', [$group, $guest]))
        ->assertRedirect()
        ->assertSessionHas('success', __('ui.groupSettings.requests.accepted', ['name' => 'Gina Guest']));

    expect($group->members()->whereKey($guest->id)->first()->pivot->role)->toBe(GroupRole::Member)
        ->and(GroupMembershipRequest::query()->count())->toBe(0);
});

it('F-03: accepting a guest who already became a member does not fail', function () {
    ['group' => $group, 'owner' => $owner, 'guest' => $guest] = requestGroup();
    GroupMembershipRequest::factory()->for($group)->for($guest)->create();
    $group->addMember($guest, GroupRole::Member, now());

    $this->actingAs($owner)->post(route('groups.membership-requests.accept', [$group, $guest]))->assertRedirect();

    expect($group->members()->whereKey($guest->id)->count())->toBe(1)
        ->and(GroupMembershipRequest::query()->count())->toBe(0);
});

it('F-03: the owner declines a request, the guest stays a guest', function () {
    ['group' => $group, 'owner' => $owner, 'guest' => $guest] = requestGroup();
    GroupMembershipRequest::factory()->for($group)->for($guest)->create();

    $this->actingAs($owner)
        ->delete(route('groups.membership-requests.decline', [$group, $guest]))
        ->assertRedirect()
        ->assertSessionHas('success', __('ui.groupSettings.requests.declined', ['name' => 'Gina Guest']));

    expect($group->hasMember($guest))->toBeFalse()
        ->and(GroupMembershipRequest::query()->count())->toBe(0);
});

it('F-03: an admin cannot resolve requests and sees none', function () {
    ['group' => $group, 'admin' => $admin, 'guest' => $guest] = requestGroup();
    GroupMembershipRequest::factory()->for($group)->for($guest)->create();

    $this->actingAs($admin)->get(route('groups.edit', $group))->assertInertia(fn (Assert $page) => $page
        ->has('page.membership_requests', 0));
    $this->actingAs($admin)->post(route('groups.membership-requests.accept', [$group, $guest]))->assertForbidden();
    $this->actingAs($admin)->delete(route('groups.membership-requests.decline', [$group, $guest]))->assertForbidden();

    expect($group->hasMember($guest))->toBeFalse()
        ->and(GroupMembershipRequest::query()->count())->toBe(1);
});

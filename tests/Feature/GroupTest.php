<?php

use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Friend;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

it('creates a group with the requesting user as owner and member', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('groups.store'), ['name' => 'Family Trip']);

    $group = Group::query()->where('name', 'Family Trip')->firstOrFail();
    $response->assertRedirect(route('groups.show', $group));
    $response->assertSessionHas('success', 'Group created');
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
    $response->assertSessionHas('success', 'Changes saved');
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
    $response->assertSessionHas('success', 'Group deleted');
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

it('F-03: shows the "New group" page with friend suggestions', function () {
    $user = User::factory()->create();
    Friend::factory()->create(['user_id' => $user->id, 'name' => 'Annika Friend']);

    $this->actingAs($user)
        ->get(route('groups.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Create')
            ->where('form.friends.0.name', 'Annika Friend')
            ->has('form.friends.0.email'));
});

it('F-03: creates a group with invitations, which can also be skipped', function () {
    Mail::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('groups.store'), [
        'name' => 'Choir',
        'invites' => [['email' => 'anna@example.com', 'name' => 'Anna'], ['email' => 'ben@example.com']],
    ]);

    $group = Group::query()->where('name', 'Choir')->firstOrFail();
    $response->assertRedirect(route('groups.show', $group));
    $response->assertSessionHas('success', 'Group created, 2 people invited');
    expect($group->members()->count())->toBe(3);
    expect($group->members()->where('email', 'ben@example.com')->first()?->pivot->accepted_at)->toBeNull();
});

it('F-03: rejects inviting oneself when creating a group', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.store'), ['name' => 'Choir', 'invites' => [['email' => $user->email]]])
        ->assertInvalid('invites.0.email');

    expect(Group::query()->count())->toBe(0);
});

it('F-03: shows polls, upcoming and past events and group lists on the group page', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Gig date']);
    Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Past gig', 'date' => now()->subWeek()->toDateString(), 'starts_at' => null]);
    Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Next gig', 'date' => now()->addWeek()->toDateString(), 'starts_at' => null]);
    Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'title' => 'Snacks']);

    $this->actingAs($owner)
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Groups/Show')
            ->where('page.own_role', 'owner')
            ->where('page.polls.0.title', 'Gig date')
            ->where('page.events.0.title', 'Next gig')
            ->where('page.events.0.is_past', false)
            ->where('page.events.1.title', 'Past gig')
            ->where('page.lists.0.title', 'Snacks')
            ->where('page.permissions.can_manage_settings', true)
            ->where('page.permissions.can_invite', true));
});

it('F-04: shows member emails to owners and admins only, members see their own email', function () {
    $owner = User::factory()->create(['name' => 'Olga', 'email' => 'olga@example.com']);
    $admin = User::factory()->create(['name' => 'Adam', 'email' => 'adam@example.com']);
    $member = User::factory()->create(['name' => 'Mia', 'email' => 'mia@example.com']);
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    foreach ([$owner, $admin] as $viewer) {
        $this->actingAs($viewer)
            ->get(route('groups.show', $group))
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.members.0.email', 'olga@example.com')
                ->where('page.members.1.email', 'adam@example.com')
                ->where('page.members.2.email', 'mia@example.com'));
    }

    $this->actingAs($member)
        ->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.members.0.name', 'Olga')
            ->where('page.members.0.role', 'owner')
            ->where('page.members.0.email', null)
            ->where('page.members.1.email', null)
            ->where('page.members.2.email', 'mia@example.com'));
});

<?php

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/**
 * Give the user claims on two items of a list attached to the event.
 *
 * @return array{0: RosterItemClaim, 1: RosterItemClaim}
 */
function claimTwoEventItems(Event $event, User $user): array
{
    $roster = Roster::factory()->create(['owner_id' => $event->organizer_id, 'event_id' => $event->id]);
    $cables = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Extension reel']);
    $drinks = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Drinks', 'quantity' => 2, 'unit' => 'crates']);

    return [
        RosterItemClaim::factory()->create(['roster_item_id' => $cables->id, 'user_id' => $user->id]),
        RosterItemClaim::factory()->create(['roster_item_id' => $drinks->id, 'user_id' => $user->id, 'quantity' => 1]),
    ];
}

/**
 * Create an upcoming event in a fresh group with an accepted plain member.
 *
 * @return array{0: Event, 1: Group, 2: User, 3: User, 4: User}
 */
function eventWithAcceptedMember(): array
{
    [$group, $owner, $admin, $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);

    return [$event, $group, $owner, $admin, $members[0]];
}

it('F-11: a decline with two claimed items shows both items in the confirmation, and both are free again afterwards', function () {
    [$event, , , , $member] = eventWithAcceptedMember();
    claimTwoEventItems($event, $member);

    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show', false)
        ->has('page.own_claims', 2)
        ->where('page.own_claims.0.item_name', 'Extension reel')
        ->where('page.own_claims.1.item_name', 'Drinks'));

    $this->actingAs($member)
        ->putJson(route('events.participation.update', $event), ['status' => 'declined'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['confirm_release' => 'Declining releases your claims: Extension reel, Drinks.']);

    expect(RosterItemClaim::query()->count())->toBe(2)
        ->and($event->fresh()->statusOf($member))->toBe(ParticipationStatus::Accepted);

    $this->actingAs($member)
        ->from(route('events.show', $event))
        ->put(route('events.participation.update', $event), ['status' => 'declined', 'confirm_release' => true])
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', 'You declined the event.');

    expect(RosterItemClaim::query()->count())->toBe(0)
        ->and($event->fresh()->statusOf($member))->toBe(ParticipationStatus::Declined);
});

it('F-11: a decline without claims needs no confirmation', function () {
    [$event, , , , $member] = eventWithAcceptedMember();

    $this->actingAs($member)
        ->put(route('events.participation.update', $event), ['status' => 'declined'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'You declined the event.');

    expect($event->fresh()->statusOf($member))->toBe(ParticipationStatus::Declined);
});

it('F-11: when an admin sets a member to "declined", the member\'s claims stay', function () {
    [$event, , , $admin, $member] = eventWithAcceptedMember();
    claimTwoEventItems($event, $member);

    $this->actingAs($admin)
        ->put(route('events.participants.update', [$event, $member]), ['status' => 'declined'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Participation updated.');

    expect($event->fresh()->statusOf($member))->toBe(ParticipationStatus::Declined)
        ->and(RosterItemClaim::query()->where('user_id', $member->id)->count())->toBe(2);
});

it('F-11: removing a participant keeps their claims', function () {
    [$event, , $owner, , $member] = eventWithAcceptedMember();
    claimTwoEventItems($event, $member);

    $this->actingAs($owner)
        ->delete(route('events.participants.destroy', [$event, $member]))
        ->assertSessionHas('success', 'Participant removed.');

    expect($event->fresh()->statusOf($member))->toBeNull()
        ->and(RosterItemClaim::query()->where('user_id', $member->id)->count())->toBe(2);
});

it('F-11: a member who declined does not see the event\'s lists', function () {
    [$event, , , , $member] = eventWithAcceptedMember();
    Roster::factory()->create(['owner_id' => $event->organizer_id, 'event_id' => $event->id]);

    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show', false)
        ->has('page.lists', 1));

    $this->actingAs($member)->put(route('events.participation.update', $event), ['status' => 'declined']);

    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show', false)
        ->where('page.own_status', 'declined')
        ->has('page.lists', 0));
});

it('F-11: a past event allows no accepting or declining', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->past()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($members[0])->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show', false)
        ->where('page.event.is_past', true)
        ->where('page.permissions.can_respond', false)
        ->where('page.permissions.can_set_status_for_others', false));

    $this->actingAs($members[0])->put(route('events.participation.update', $event), ['status' => 'accepted'])->assertForbidden();
    $this->actingAs($members[0])->put(route('events.participation.update', $event), ['status' => 'declined'])->assertForbidden();
    $this->actingAs($owner)->put(route('events.participants.update', [$event, $members[0]]), ['status' => 'accepted'])->assertForbidden();

    expect($event->participations()->count())->toBe(0);
});

it('F-11: every group member accepts, also without a previous participation status', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($members[0])
        ->put(route('events.participation.update', $event), ['status' => 'accepted'])
        ->assertSessionHas('success', 'You accepted the event.');

    expect($event->fresh()->statusOf($members[0]))->toBe(ParticipationStatus::Accepted);

    $this->actingAs($members[0])
        ->put(route('events.participation.update', $event), ['status' => 'pending'])
        ->assertSessionHasErrors('status');

    $this->actingAs(User::factory()->create())
        ->put(route('events.participation.update', $event), ['status' => 'accepted'])
        ->assertNotFound();
});

it('F-11: members set the status for others only when the group option allows it', function () {
    [$group, $owner, , $members] = eventGroup(2);
    [$member, $other] = $members;
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show', false)
        ->where('page.permissions.can_set_status_for_others', false)
        ->where('page.permissions.can_remove_participants', false));

    $this->actingAs($member)->put(route('events.participants.update', [$event, $other]), ['status' => 'accepted'])->assertForbidden();
    $this->actingAs($member)->delete(route('events.participants.destroy', [$event, $other]))->assertForbidden();

    $group->update(['members_can_set_status' => true]);

    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show', false)
        ->where('page.permissions.can_set_status_for_others', true)
        ->where('page.permissions.can_remove_participants', false));

    $this->actingAs($member)
        ->put(route('events.participants.update', [$event, $other]), ['status' => 'accepted'])
        ->assertSessionHasNoErrors();

    expect($event->fresh()->statusOf($other))->toBe(ParticipationStatus::Accepted);
});

it('F-11: organizer, owner and admins set the status for others, but only for group members', function () {
    [$event, , $owner, $admin, $member] = eventWithAcceptedMember();

    $this->actingAs($owner)->put(route('events.participants.update', [$event, $member]), ['status' => 'pending'])->assertSessionHasNoErrors();
    expect($event->fresh()->statusOf($member))->toBe(ParticipationStatus::Pending);

    $this->actingAs($admin)->put(route('events.participants.update', [$event, $member]), ['status' => 'accepted'])->assertSessionHasNoErrors();
    expect($event->fresh()->statusOf($member))->toBe(ParticipationStatus::Accepted);

    $this->actingAs($owner)
        ->put(route('events.participants.update', [$event, User::factory()->create()]), ['status' => 'accepted'])
        ->assertSessionHasErrors('status');
});

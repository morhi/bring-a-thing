<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Enums\PollType;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/**
 * Create a group with an owner and the given plain members.
 *
 * @param  array<int, User>  $members
 */
function listsGroupWith(User $owner, array $members = []): Group
{
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    return $group;
}

/**
 * Create an event in the group with a list owned by the organizer, and give each person the given status.
 *
 * @param  array<int, array{0: User, 1: ParticipationStatus}>  $statuses
 * @return array{0: Event, 1: Roster}
 */
function listsEventListIn(Group $group, User $organizer, array $statuses = []): array
{
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $organizer->id]);

    foreach ($statuses as [$person, $status]) {
        EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $person->id, 'status' => $status]);
    }

    $roster = Roster::factory()->create(['owner_id' => $organizer->id, 'event_id' => $event->id]);

    return [$event, $roster];
}

it('F-13 hides another member\'s standalone list from a group member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    listsGroupWith($owner, [$member]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($member)->get(route('rosters.show', $roster))->assertNotFound();
});

it('F-13 shows a standalone list to a person with a claim on it', function () {
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $item->claims()->create(['user_id' => $claimant->id, 'quantity' => null]);

    $this->actingAs($claimant)->get(route('rosters.show', $roster))->assertOk();
});

it('F-13 shows an event list to an accepted person and hides it from a person who declined', function () {
    $organizer = User::factory()->create();
    $accepted = User::factory()->create();
    $declined = User::factory()->create();
    $group = listsGroupWith($organizer, [$accepted, $declined]);
    [$event, $roster] = listsEventListIn($group, $organizer, [
        [$accepted, ParticipationStatus::Accepted],
        [$declined, ParticipationStatus::Declined],
    ]);

    $this->actingAs($accepted)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->component('Events/Show', false)->has('page.lists', 1));
    $this->actingAs($accepted)->get(route('rosters.show', $roster))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.roster.placement', 'event')
            ->where('page.roster.event.slug', $event->slug));

    $this->actingAs($declined)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->component('Events/Show', false)->has('page.lists', 0));
    $this->actingAs($declined)->get(route('rosters.show', $roster))->assertNotFound();
});

it('F-13 shows an event list to a pending participant', function () {
    $organizer = User::factory()->create();
    $pending = User::factory()->create();
    $group = listsGroupWith($organizer, [$pending]);
    [, $roster] = listsEventListIn($group, $organizer, [[$pending, ParticipationStatus::Pending]]);

    $this->actingAs($pending)->get(route('rosters.show', $roster))->assertOk();
});

it('F-13 keeps a group member without participation out of every direct event list URL', function () {
    $organizer = User::factory()->create();
    $member = User::factory()->create();
    $group = listsGroupWith($organizer, [$member]);
    [, $roster] = listsEventListIn($group, $organizer);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($member)->get(route('rosters.show', $roster))->assertNotFound();
    $this->actingAs($member)->post(route('rosters.items.claim.store', [$roster, $item]))->assertForbidden();
    $this->actingAs($member)->post(route('rosters.comments.store', $roster), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($member)->post(route('rosters.items.comments.store', [$roster, $item]), ['body' => 'Hi'])->assertForbidden();
    $this->actingAs($member)->post(route('rosters.items.store', $roster), ['name' => 'Cake'])->assertForbidden();
    $this->actingAs($member)->patch(route('rosters.update', $roster), ['title' => 'Renamed'])->assertForbidden();

    expect($item->claims()->count())->toBe(0);
    expect($roster->comments()->count())->toBe(0);
});

it('F-13 keeps a group member without participation out of an event list through its share link', function () {
    $organizer = User::factory()->create();
    $member = User::factory()->create();
    $group = listsGroupWith($organizer, [$member]);
    [, $roster] = listsEventListIn($group, $organizer, [[$member, ParticipationStatus::Declined]]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($member)->get(route('shared-rosters.show', $roster->share_token))->assertNotFound();
    $this->actingAs($member)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]))->assertNotFound();

    expect($item->claims()->count())->toBe(0);
});

it('F-13 lets the list owner and a group admin see an event list without participation', function () {
    $organizer = User::factory()->create();
    $admin = User::factory()->create();
    $group = listsGroupWith($organizer, [$admin]);
    $group->members()->updateExistingPivot($admin, ['role' => GroupRole::Admin]);
    [$event, $roster] = listsEventListIn($group, $organizer, [[$admin, ParticipationStatus::Declined]]);
    $event->participations()->where('user_id', $organizer->id)->update(['status' => ParticipationStatus::Declined]);

    $this->actingAs($organizer)->get(route('rosters.show', $roster))->assertOk();
    $this->actingAs($admin)->get(route('rosters.show', $roster))->assertOk();
});

it('F-13 creates a list attached to an event for a participant', function () {
    $organizer = User::factory()->create();
    $pending = User::factory()->create();
    $group = listsGroupWith($organizer, [$pending]);
    [$event] = listsEventListIn($group, $organizer, [[$pending, ParticipationStatus::Pending]]);

    $this->actingAs($pending)->post(route('rosters.store'), ['title' => 'Gig gear', 'event_id' => $event->id])
        ->assertRedirect()
        ->assertSessionHas('success', 'Roster created.');

    $roster = Roster::query()->where('title', 'Gig gear')->sole();
    expect($roster->event_id)->toBe($event->id);
    expect($roster->group_id)->toBeNull();
});

it('F-13 forbids creating an event list for a member without participation', function () {
    $organizer = User::factory()->create();
    $member = User::factory()->create();
    $group = listsGroupWith($organizer, [$member]);
    [$event] = listsEventListIn($group, $organizer);

    $this->actingAs($member)->post(route('rosters.store'), ['title' => 'Gig gear', 'event_id' => $event->id])->assertForbidden();
});

it('F-13 rejects a list placed in a group and an event at once', function () {
    $organizer = User::factory()->create();
    $group = listsGroupWith($organizer);
    [$event] = listsEventListIn($group, $organizer);

    $this->actingAs($organizer)->post(route('rosters.store'), ['title' => 'Both', 'group_id' => $group->id, 'event_id' => $event->id])
        ->assertSessionHasErrors('group_id');
});

it('F-13 shows the new list page with the placement it was opened from', function () {
    $organizer = User::factory()->create();
    $group = listsGroupWith($organizer);
    [$event] = listsEventListIn($group, $organizer);

    $this->actingAs($organizer)->get(route('rosters.create', ['event' => $event->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Create', false)
            ->where('form.event.slug', $event->slug)
            ->has('form.groups', 1));
});

it('F-13 lets a group admin edit and delete another member\'s group list', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group = listsGroupWith($owner, [$admin, $member]);
    $group->members()->updateExistingPivot($admin, ['role' => GroupRole::Admin]);
    $roster = Roster::factory()->create(['owner_id' => $member->id, 'group_id' => $group->id]);

    $this->actingAs($admin)->patch(route('rosters.update', $roster), ['title' => 'Renamed'])->assertRedirect();
    expect($roster->fresh()->title)->toBe('Renamed');

    $this->actingAs($admin)->delete(route('rosters.destroy', $roster))->assertSessionHas('success', 'Roster deleted.');
    $this->assertModelMissing($roster);
});

it('F-13 marks a list as past when its date has passed', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'date' => now()->subDay()->toDateString()]);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->component('Rosters/Show', false)->where('page.roster.is_past', true));
});

it('F-15 rejects a further claim on 10 chairs claimed with 6 and 4', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();
    $group = listsGroupWith($owner, [$first, $second, $third]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Chairs', 'quantity' => 10]);

    $this->actingAs($first)->post(route('rosters.items.claim.store', [$roster, $item]), ['quantity' => 6])->assertSessionHasNoErrors();
    $this->actingAs($second)->post(route('rosters.items.claim.store', [$roster, $item]), ['quantity' => 4])->assertSessionHasNoErrors();
    $this->actingAs($third)->post(route('rosters.items.claim.store', [$roster, $item]), ['quantity' => 1])
        ->assertSessionHasErrors(['quantity' => 'Nothing is left to claim.']);

    expect($item->claims()->count())->toBe(2);
});

it('F-15 names the remaining quantity when a claim exceeds it', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $group = listsGroupWith($owner, [$first, $second]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10, 'unit' => 'chairs']);
    $item->claims()->create(['user_id' => $first->id, 'quantity' => 8]);

    $this->actingAs($second)->post(route('rosters.items.claim.store', [$roster, $item]), ['quantity' => 5])
        ->assertSessionHasErrors(['quantity' => 'Only 2 chairs left to claim.']);
});

it('F-15 shows who claimed how much and what is still missing', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mia']);
    $group = listsGroupWith($owner, [$member]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);
    $item->claims()->create(['user_id' => $member->id, 'quantity' => 6]);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.items.0.claims.0.user_name', 'Mia')
            ->where('page.items.0.claims.0.quantity', 6)
            ->where('page.items.0.remaining_quantity', 4)
            ->where('page.items.0.is_fully_claimed', false)
            ->where('page.is_all_taken', false));
});

it('F-15 offers no claim to others on a task with one claimant', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $group = listsGroupWith($owner, [$first, $second]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $item->claims()->create(['user_id' => $first->id, 'quantity' => null]);

    $this->actingAs($second)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.items.0.is_task', true)
            ->where('page.items.0.can_claim', false)
            ->where('page.is_all_taken', true));
    $this->actingAs($first)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->component('Rosters/Show', false)->where('page.items.0.can_claim', true));

    $this->actingAs($second)->post(route('rosters.items.claim.store', [$roster, $item]))->assertSessionHasErrors('quantity');
});

it('F-15 lets an admin claim an item for a member, and the item shows the member as claiming', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create(['name' => 'Mia']);
    $group = listsGroupWith($owner, [$admin, $member]);
    $group->members()->updateExistingPivot($admin, ['role' => GroupRole::Admin]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $this->actingAs($admin)->post(route('rosters.items.claim.store', [$roster, $item]), ['user_id' => $member->id])
        ->assertSessionHas('success', 'Claim saved.');

    $this->actingAs($admin)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.items.0.claims.0.user_id', $member->id)
            ->where('page.items.0.claims.0.user_name', 'Mia')
            ->where('page.permissions.can_manage_claims', true)
            ->has('page.claim_targets', 3));
});

it('F-15 shows a pending person the note about accepting instead of the claim action on an event list', function () {
    $organizer = User::factory()->create();
    $pending = User::factory()->create();
    $group = listsGroupWith($organizer, [$pending]);
    [, $roster] = listsEventListIn($group, $organizer, [[$pending, ParticipationStatus::Pending]]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($pending)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.permissions.needs_acceptance', true)
            ->where('page.permissions.can_claim', false)
            ->where('page.items.0.can_claim', false));

    $this->actingAs($pending)->post(route('rosters.items.claim.store', [$roster, $item]))->assertForbidden();
    expect($item->claims()->count())->toBe(0);
});

it('F-15 lets an accepted participant claim on an event list', function () {
    $organizer = User::factory()->create();
    $accepted = User::factory()->create();
    $group = listsGroupWith($organizer, [$accepted]);
    [, $roster] = listsEventListIn($group, $organizer, [[$accepted, ParticipationStatus::Accepted]]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($accepted)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.permissions.needs_acceptance', false)
            ->where('page.items.0.can_claim', true));

    $this->actingAs($accepted)->post(route('rosters.items.claim.store', [$roster, $item]))->assertSessionHas('success', 'Claim saved.');
    expect($item->claims()->where('user_id', $accepted->id)->exists())->toBeTrue();
});

it('F-15 lets the organizer claim on an event list only for accepted participants', function () {
    $organizer = User::factory()->create();
    $accepted = User::factory()->create();
    $pending = User::factory()->create();
    $group = listsGroupWith($organizer, [$accepted, $pending]);
    [, $roster] = listsEventListIn($group, $organizer, [
        [$accepted, ParticipationStatus::Accepted],
        [$pending, ParticipationStatus::Pending],
    ]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 4]);

    $this->actingAs($organizer)->post(route('rosters.items.claim.store', [$roster, $item]), ['user_id' => $pending->id, 'quantity' => 1])
        ->assertForbidden();
    $this->actingAs($organizer)->post(route('rosters.items.claim.store', [$roster, $item]), ['user_id' => $accepted->id, 'quantity' => 1])
        ->assertSessionHas('success', 'Claim saved.');

    $this->actingAs($organizer)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->has('page.claim_targets', 2));
});

it('F-15 lets a person whose acceptance went back to pending remove their own claim', function () {
    $organizer = User::factory()->create();
    $pending = User::factory()->create();
    $group = listsGroupWith($organizer, [$pending]);
    [, $roster] = listsEventListIn($group, $organizer, [[$pending, ParticipationStatus::Pending]]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $item->claims()->create(['user_id' => $pending->id, 'quantity' => null]);

    $this->actingAs($pending)->delete(route('rosters.items.claim.destroy', [$roster, $item]))->assertSessionHas('success', 'Claim removed.');

    expect($item->claims()->count())->toBe(0);
});

/**
 * Create a group list linked to a day of an attendance poll of the same group.
 *
 * @return array{0: Roster, 1: PollOption, 2: Group}
 */
function listsAttendanceRoster(User $owner, array $members): array
{
    $group = listsGroupWith($owner, $members);
    $poll = Poll::factory()->create(['type' => PollType::Attendance, 'group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'attendance_poll_option_id' => $option->id]);

    return [$roster, $option, $group];
}

it('F-18 asks a person with no for the linked day to confirm before claiming, and a person with yes not', function () {
    $owner = User::factory()->create();
    $absent = User::factory()->create();
    $present = User::factory()->create();
    [$roster, $option] = listsAttendanceRoster($owner, [$absent, $present]);
    $option->responses()->create(['user_id' => $absent->id, 'status' => PollResponseStatus::No]);
    $option->responses()->create(['user_id' => $present->id, 'status' => PollResponseStatus::Yes]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($absent)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.items.0.attendance_warning', true)
            ->where('page.items.0.attendance_day.poll_slug', $option->poll->slug)
            ->where('page.roster.attendance_day.option_id', $option->id));
    $this->actingAs($present)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->component('Rosters/Show', false)->where('page.items.0.attendance_warning', false));

    $this->actingAs($absent)->post(route('rosters.items.claim.store', [$roster, $item]))->assertSessionHas('success', 'Claim saved.');
});

it('F-18 warns about a person without an answer and exposes who is available for claiming for others', function () {
    $owner = User::factory()->create();
    $silent = User::factory()->create();
    $present = User::factory()->create();
    [$roster, $option] = listsAttendanceRoster($owner, [$silent, $present]);
    $option->responses()->create(['user_id' => $present->id, 'status' => PollResponseStatus::Yes]);
    RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($silent)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->component('Rosters/Show', false)->where('page.items.0.attendance_warning', true));
    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.items.0.attendance_day.available_user_ids', [$present->id]));
});

it('F-18 offers only attendance polls of the same group', function () {
    $owner = User::factory()->create();
    [$roster, $option, $group] = listsAttendanceRoster($owner, []);
    PollOption::factory()->create(['poll_id' => Poll::factory()->create(['type' => PollType::DateFinder, 'group_id' => $group->id])->id]);
    PollOption::factory()->create(['poll_id' => Poll::factory()->create(['type' => PollType::Attendance])->id]);

    $this->actingAs($owner)->get(route('rosters.edit', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Edit', false)
            ->has('form.attendance_options', 1)
            ->where('form.attendance_options.0.id', $option->id));
});

it('F-19 lets a guest claim an item, log in through the login link and then see their claim in the list', function () {
    $owner = User::factory()->create();
    $guest = User::factory()->create(['name' => 'Gina']);
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $this->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]))->assertRedirect(route('welcome'));

    $this->get(URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]))
        ->assertRedirect(route('shared-rosters.show', $roster->share_token));

    $this->get(route('shared-rosters.show', $roster->share_token))->assertRedirect(route('rosters.show', $roster));
    $this->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Rosters/Show', false)
            ->where('page.items.0.claims.0.user_name', 'Gina'));
});

it('F-19 shows the note that the old link is no longer valid after regenerating', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $oldToken = $roster->enableSharing();

    $this->actingAs($owner)->post(route('rosters.sharing.regenerate', $roster))->assertRedirect();
    auth()->logout();

    $this->get(route('shared-rosters.show', $oldToken))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Shared/RosterInvalid', false));
    $this->get(route('shared-rosters.show', $roster->fresh()->share_token))->assertOk();
});

it('F-19 lets a person outside the group claim on an event list through its share link', function () {
    $organizer = User::factory()->create();
    $outsider = User::factory()->create();
    $group = listsGroupWith($organizer);
    [, $roster] = listsEventListIn($group, $organizer);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($outsider)->get(route('shared-rosters.show', $roster->share_token))->assertOk();
    $this->actingAs($outsider)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]))
        ->assertSessionHas('success', 'Claim saved.');
});

it('F-19 keeps a pending participant from claiming on an event list through its share link', function () {
    $organizer = User::factory()->create();
    $pending = User::factory()->create();
    $group = listsGroupWith($organizer, [$pending]);
    [, $roster] = listsEventListIn($group, $organizer, [[$pending, ParticipationStatus::Pending]]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($pending)->get(route('shared-rosters.show', $roster->share_token))->assertRedirect(route('rosters.show', $roster));
    $this->actingAs($pending)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]))
        ->assertSessionHas('error', 'Accept the event first to claim items.');

    expect($item->claims()->count())->toBe(0);
});

it('F-19 hides other people\'s email addresses and user IDs on the shared list', function () {
    $owner = User::factory()->create();
    $claimant = User::factory()->create(['name' => 'Mia', 'email' => 'mia@example.com']);
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $item->claims()->create(['user_id' => $claimant->id, 'quantity' => null]);

    $this->get(route('shared-rosters.show', $roster->share_token))
        ->assertDontSee('mia@example.com')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shared/RosterShow')
            ->where('roster.items.0.claims.0.user_name', 'Mia')
            ->where('roster.items.0.claims.0.user_id', null));
});

it('F-17 duplicates an event list into the same event without claims and without a date', function () {
    $organizer = User::factory()->create();
    $group = listsGroupWith($organizer);
    [$event, $roster] = listsEventListIn($group, $organizer);
    $roster->update(['date' => now()->addWeek()->toDateString()]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $item->claims()->create(['user_id' => $organizer->id, 'quantity' => null]);

    $this->actingAs($organizer)->post(route('rosters.duplicate', $roster))->assertSessionHas('success', 'Roster duplicated.');

    $copy = Roster::query()->whereKeyNot($roster->id)->sole();
    expect($copy->event_id)->toBe($event->id);
    expect($copy->date)->toBeNull();
    expect($copy->items()->withCount('claims')->first()->claims_count)->toBe(0);
});

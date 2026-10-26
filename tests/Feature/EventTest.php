<?php

use App\Actions\Events\CreateEventFromPollOption;
use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner, an admin and the given number of plain members.
 *
 * @return array{0: Group, 1: User, 2: User, 3: array<int, User>}
 */
function eventGroup(int $memberCount = 2): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());

    $members = User::factory()->count($memberCount)->create()->all();

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    return [$group, $owner, $admin, $members];
}

/**
 * Valid input for the event form.
 *
 * @return array<string, mixed>
 */
function eventInput(array $overrides = []): array
{
    return array_merge([
        'title' => 'Gig at the Rex',
        'description' => 'Bring your own cables.',
        'location' => 'Rex, Bern',
        'date' => now()->addMonth()->format('Y-m-d'),
        'starts_at' => '20:00',
        'end_date' => null,
        'ends_at' => null,
    ], $overrides);
}

it('F-10: a directly created event in a group with five members shows one accepted organizer and four pending responses', function () {
    [$group, , , $members] = eventGroup(3);
    $organizer = $members[0];

    $this->actingAs($organizer)
        ->post(route('events.store', $group), eventInput())
        ->assertRedirect()
        ->assertSessionHas('success', 'Event created');

    $event = Event::query()->sole();

    expect($event->organizer_id)->toBe($organizer->id);

    $this->actingAs($organizer)
        ->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('page.accepted_count', 1)
            ->where('page.pending_count', 4)
            ->where('page.declined_count', 0)
            ->where('page.own_status', 'accepted')
            ->where('page.event.title', 'Gig at the Rex')
            ->where('page.event.starts_at', '20:00')
            ->has('page.participants', 5));
});

it('F-10: after moving the event from 12.03. to 19.03., all acceptances are pending again', function () {
    $this->travelTo('2027-01-10 12:00');
    [$group, $owner, , $members] = eventGroup(2);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12']);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);
    EventParticipation::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $members[1]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['title' => $event->title, 'date' => '2027-03-19', 'starts_at' => null]))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', 'Event moved. All acceptances are pending again.');

    expect($event->fresh()->date->format('Y-m-d'))->toBe('2027-03-19')
        ->and($event->participations()->where('status', ParticipationStatus::Accepted)->count())->toBe(0)
        ->and($event->participations()->where('status', ParticipationStatus::Pending)->count())->toBe(2)
        ->and($event->participations()->where('status', ParticipationStatus::Declined)->count())->toBe(1);
});

it('F-10: changing only the time also resets acceptances, changing the location does not', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'starts_at' => '19:00']);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);
    $input = eventInput(['date' => $event->date->format('Y-m-d'), 'starts_at' => '19:00']);

    $this->actingAs($owner)->patch(route('events.update', $event), [...$input, 'location' => 'Elsewhere']);
    expect($participation->fresh()->status)->toBe(ParticipationStatus::Accepted);

    $this->actingAs($owner)->patch(route('events.update', $event), [...$input, 'starts_at' => '20:30']);
    expect($participation->fresh()->status)->toBe(ParticipationStatus::Pending);
});

it('F-10: deleting an event with a list also removes the list', function () {
    [$group, $owner] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $owner->id]);

    $this->actingAs($owner)
        ->delete(route('events.destroy', $event))
        ->assertRedirect(route('groups.show', $group))
        ->assertSessionHas('success', 'Event deleted');

    expect(Event::query()->count())->toBe(0)
        ->and(Roster::query()->count())->toBe(0)
        ->and(RosterItem::query()->count())->toBe(0)
        ->and(RosterItemClaim::query()->count())->toBe(0);

    $this->actingAs($owner)->get(route('rosters.show', $roster))->assertNotFound();
});

it('F-10: deleting an event removes its follow-up polls and child events with their lists', function () {
    [$group, $owner] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'event_id' => $event->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $child = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $event->id, 'origin_poll_option_id' => $option->id]);
    Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $child->id]);
    $unrelated = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($owner)->delete(route('events.destroy', $event))->assertRedirect();

    expect(Event::query()->pluck('id')->all())->toBe([$unrelated->id])
        ->and(Poll::query()->count())->toBe(0)
        ->and(Roster::query()->count())->toBe(0);
});

it('F-10: organizer, owner and admins edit and delete the event, other members do not', function () {
    [$group, $owner, $admin, $members] = eventGroup(2);
    [$organizer, $member] = $members;
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);
    $input = eventInput(['title' => 'Renamed']);

    $this->actingAs($member)->patch(route('events.update', $event), $input)->assertForbidden();
    $this->actingAs($member)->delete(route('events.destroy', $event))->assertForbidden();
    $this->actingAs($member)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($member)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('page.permissions.can_edit', false)
            ->where('page.permissions.can_delete', false));

    foreach ([$organizer, $owner, $admin] as $manager) {
        $this->actingAs($manager)->patch(route('events.update', $event), $input)->assertRedirect();
        $this->actingAs($manager)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Create')
                ->where('form.event.title', 'Renamed')
                ->where('form.member_count', 4));
    }

    $this->actingAs($admin)->delete(route('events.destroy', $event))->assertRedirect();
    expect(Event::query()->count())->toBe(0);
});

it('F-10: an organizer who left the group can neither update, delete nor set participation for others', function () {
    [$group, , , $members] = eventGroup(2);
    [$organizer, $member] = $members;
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);
    $group->members()->detach($organizer);

    $this->actingAs($organizer)->patch(route('events.update', $event), eventInput())->assertForbidden();
    $this->actingAs($organizer)->delete(route('events.destroy', $event))->assertForbidden();
    $this->actingAs($organizer)
        ->put(route('events.participants.update', [$event, $member]), ['status' => 'declined'])
        ->assertForbidden();
    expect(Event::query()->count())->toBe(1);
});

it('F-10: an organizer who is still a member sets participation for others', function () {
    [$group, , , $members] = eventGroup(2);
    [$organizer, $member] = $members;
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);

    expect($event->isManagedBy($organizer))->toBeTrue();
    $this->actingAs($organizer)
        ->put(route('events.participants.update', [$event, $member]), ['status' => 'declined'])
        ->assertRedirect();
});

it('F-10: shows "Not found" to people outside the group', function () {
    [$group, $owner] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs(User::factory()->create())->get(route('events.show', $event))->assertNotFound();
    $this->actingAs(User::factory()->create())->post(route('events.store', $group), eventInput())->assertForbidden();
});

it('F-10: shows the "New event" page to group members with the audience size', function () {
    [$group, , , $members] = eventGroup(2);

    $this->actingAs($members[0])
        ->get(route('events.create', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Create')
            ->where('form.group_slug', $group->slug)
            ->where('form.member_count', 4)
            ->where('form.event', null));
});

it('F-10: requires a title and a start date, and an end that is not before the start', function () {
    [$group, $owner] = eventGroup(0);

    $this->actingAs($owner)
        ->post(route('events.store', $group), ['title' => '', 'date' => ''])
        ->assertSessionHasErrors(['title', 'date']);

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['date' => '2027-03-12', 'end_date' => '2027-03-11']))
        ->assertSessionHasErrors('end_date');

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['date' => '2027-03-12', 'starts_at' => '20:00', 'end_date' => '2027-03-12', 'ends_at' => '19:00']))
        ->assertSessionHasErrors('ends_at');

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['starts_at' => null, 'location' => null, 'description' => null]))
        ->assertSessionHasNoErrors();

    expect(Event::query()->sole()->starts_at)->toBeNull();
});

it('F-10: an event counts as past once its start has passed', function () {
    $this->travelTo('2027-03-12 19:00');
    [$group, $owner] = eventGroup(0);

    $earlierToday = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12', 'starts_at' => '18:00']);
    $laterToday = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12', 'starts_at' => '20:00']);
    $todayWithoutTime = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12']);
    $yesterday = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-11']);

    expect($earlierToday->isPast())->toBeTrue()
        ->and($laterToday->isPast())->toBeFalse()
        ->and($todayWithoutTime->isPast())->toBeFalse()
        ->and($yesterday->isPast())->toBeTrue();
});

it('F-10: shows lists to participants and follow-up polls to accepted participants only', function () {
    [$group, $owner, , $members] = eventGroup(2);
    [$pending, $declined] = $members;
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id]);
    EventParticipation::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $declined->id]);
    Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);
    Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'event_id' => $event->id]);
    Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $event->id]);

    $this->actingAs($owner)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show')
        ->has('page.lists', 1)
        ->has('page.follow_up_polls', 1)
        ->has('page.child_events', 1)
        ->where('page.permissions.can_create_follow_up_poll', true));

    $this->actingAs($pending)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show')
        ->has('page.lists', 1)
        ->has('page.follow_up_polls', 0)
        ->where('page.permissions.can_create_follow_up_poll', false));

    $this->actingAs($declined)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->component('Events/Show')
        ->has('page.lists', 0)
        ->has('page.follow_up_polls', 0));
});

it('F-09: creating an event from a poll option derives participation from the responses', function () {
    [$group, $owner, , $members] = eventGroup(3);
    [$yes, $maybe, $no] = $members;
    $source = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    foreach ($members as $member) {
        EventParticipation::factory()->accepted()->create(['event_id' => $source->id, 'user_id' => $member->id]);
    }

    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'event_id' => $source->id, 'title' => 'Rehearsal']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-14', 'starts_at' => '19:00', 'ends_at' => '22:00']);
    $option->responses()->create(['user_id' => $yes->id, 'status' => PollResponseStatus::Yes]);
    $option->responses()->create(['user_id' => $maybe->id, 'status' => PollResponseStatus::Maybe]);
    $option->responses()->create(['user_id' => $no->id, 'status' => PollResponseStatus::No]);

    $event = app(CreateEventFromPollOption::class)->handle($option);

    expect($event->title)->toBe('Rehearsal')
        ->and($event->date->format('Y-m-d'))->toBe('2027-03-14')
        ->and(substr($event->starts_at, 0, 5))->toBe('19:00')
        ->and(substr($event->ends_at, 0, 5))->toBe('22:00')
        ->and($event->organizer_id)->toBe($owner->id)
        ->and($event->origin_poll_option_id)->toBe($option->id)
        ->and($event->parent_event_id)->toBe($source->id)
        ->and($event->statusOf($yes))->toBe(ParticipationStatus::Accepted)
        ->and($event->statusOf($maybe))->toBe(ParticipationStatus::Pending)
        ->and($event->statusOf($no))->toBeNull()
        ->and($event->statusOf($owner))->toBeNull();
});

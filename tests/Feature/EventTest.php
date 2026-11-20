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
use Illuminate\Support\Facades\DB;
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

it('F-10: after moving the event from 12.03. to 19.03. and choosing reset or keep, acceptances follow the choice and claims stay', function (bool $reset) {
    $this->travelTo('2027-01-10 12:00');
    [$group, $owner, , $members] = eventGroup(2);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12']);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);
    EventParticipation::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $members[1]->id]);
    $item = RosterItem::factory()->create(['roster_id' => Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id])->id]);
    RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $members[0]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['title' => $event->title, 'date' => '2027-03-19', 'starts_at' => null, 'reset_acceptances' => $reset]))
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', __($reset ? 'ui.event.edit.reset' : 'ui.event.edit.movedKept'));

    expect($event->fresh()->date->format('Y-m-d'))->toBe('2027-03-19')
        ->and($event->participations()->where('status', ParticipationStatus::Accepted)->count())->toBe($reset ? 0 : 2)
        ->and($event->participations()->where('status', ParticipationStatus::Pending)->count())->toBe($reset ? 2 : 0)
        ->and($event->participations()->where('status', ParticipationStatus::Declined)->count())->toBe(1)
        ->and(RosterItemClaim::query()->count())->toBe(1);
})->with([[true], [false]]);

it('F-10: a stored time with seconds equal to the sent time is no move, even when the form says reset', function (?bool $reset) {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'starts_at' => '18:00', 'ends_at' => '20:00']);
    DB::table('events')->where('id', $event->id)->update(['starts_at' => '18:00:00', 'ends_at' => '20:00:00']);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput([
            'date' => $event->date->format('Y-m-d'),
            'starts_at' => '18:00',
            'ends_at' => '20:00',
            'location' => 'Elsewhere',
            ...($reset === null ? [] : ['reset_acceptances' => $reset]),
        ]))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('ui.event.edit.saved'));

    expect($participation->fresh()->status)->toBe(ParticipationStatus::Accepted)
        ->and($event->fresh()->location)->toBe('Elsewhere');
})->with([[true], [null]]);

it('F-10: changing only the start time needs the choice and resets or keeps accordingly', function (bool $reset) {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'starts_at' => '19:00']);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['date' => $event->date->format('Y-m-d'), 'starts_at' => '20:30', 'reset_acceptances' => $reset]))
        ->assertSessionHasNoErrors();

    expect($participation->fresh()->status)->toBe($reset ? ParticipationStatus::Pending : ParticipationStatus::Accepted);
})->with([[true], [false]]);

it('F-10: setting or clearing the start time counts as a move', function (?string $before, ?string $after) {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'starts_at' => $before]);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);
    $input = eventInput(['date' => $event->date->format('Y-m-d'), 'starts_at' => $after]);

    $this->actingAs($owner)->patch(route('events.update', $event), $input)->assertSessionHasErrors('reset_acceptances');

    $this->actingAs($owner)->patch(route('events.update', $event), [...$input, 'reset_acceptances' => true])->assertSessionHasNoErrors();

    expect($participation->fresh()->status)->toBe(ParticipationStatus::Pending);
})->with([[null, '19:00'], ['19:00', null]]);

it('F-10: moving the event without a valid reset choice is rejected and saves nothing', function (array $choice) {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12']);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['date' => '2027-03-19', ...$choice]))
        ->assertSessionHasErrors('reset_acceptances');

    expect($event->fresh()->date->format('Y-m-d'))->toBe('2027-03-12')
        ->and($participation->fresh()->status)->toBe(ParticipationStatus::Accepted);
})->with([[[]], [['reset_acceptances' => 'maybe']]]);

it('F-10: changing only the end keeps acceptances without a choice', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12', 'starts_at' => '19:00']);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['date' => '2027-03-12', 'starts_at' => '19:00', 'end_date' => '2027-03-13', 'ends_at' => '12:00']))
        ->assertSessionHasNoErrors();

    expect($participation->fresh()->status)->toBe(ParticipationStatus::Accepted)
        ->and($event->fresh()->end_date->format('Y-m-d'))->toBe('2027-03-13');
});

it('F-10: an event without a start time keeps its acceptances when only the title changes', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'starts_at' => null]);
    $participation = EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $members[0]->id]);

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['title' => 'Renamed', 'date' => $event->date->format('Y-m-d'), 'starts_at' => null]))
        ->assertSessionHasNoErrors();

    expect($participation->fresh()->status)->toBe(ParticipationStatus::Accepted)
        ->and($event->fresh()->title)->toBe('Renamed');
});

it('F-10: end date and end time are stored as sent, and an update without end date falls back to the start date', function () {
    [$group, $owner] = eventGroup(0);

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['date' => '2027-03-12', 'starts_at' => '18:00', 'end_date' => '2027-03-12', 'ends_at' => '20:00']))
        ->assertSessionHasNoErrors();
    $event = Event::query()->sole();

    expect($event->end_date->format('Y-m-d'))->toBe('2027-03-12')
        ->and(substr($event->ends_at, 0, 5))->toBe('20:00');

    $this->actingAs($owner)
        ->patch(route('events.update', $event), eventInput(['date' => '2027-03-12', 'starts_at' => null, 'end_date' => null, 'ends_at' => null, 'reset_acceptances' => true]))
        ->assertSessionHasNoErrors();

    expect($event->fresh()->end_date->format('Y-m-d'))->toBe('2027-03-12')
        ->and($event->fresh()->ends_at)->toBeNull();
});

it('F-10: the edit form hands out times without seconds', function () {
    [$group, $owner] = eventGroup(0);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'starts_at' => '18:00']);
    DB::table('events')->where('id', $event->id)->update(['starts_at' => '18:00:00']);

    $this->actingAs($owner)->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page
        ->where('form.event.starts_at', '18:00')
        ->where('form.event.end_date', $event->date->format('Y-m-d')));
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
    $input = eventInput(['title' => 'Renamed', 'date' => $event->date->format('Y-m-d'), 'starts_at' => null]);
    Roster::factory()->create(['event_id' => $event->id, 'group_id' => $group->id]);
    Poll::factory()->create(['event_id' => $event->id, 'group_id' => $group->id, 'organizer_id' => $owner->id]);
    Event::factory()->create(['parent_event_id' => $event->id, 'group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($member)->patch(route('events.update', $event), $input)->assertForbidden();
    $this->actingAs($member)->delete(route('events.destroy', $event))->assertForbidden();
    $this->actingAs($member)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($member)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Show')
            ->where('page.permissions.can_edit', false));

    foreach ([$organizer, $owner, $admin] as $manager) {
        $this->actingAs($manager)->patch(route('events.update', $event), $input)->assertRedirect();
        $this->actingAs($manager)
            ->get(route('events.edit', $event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/Create')
                ->where('form.event.title', 'Renamed')
                ->where('form.member_count', 4)
                ->where('form.can_delete', true)
                ->where('form.delete_counts', ['lists' => 1, 'follow_up_polls' => 1, 'child_events' => 1]));
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

it('F-10: an empty end date defaults to the start date, an invalid one is rejected', function (mixed $endDate, ?string $stored) {
    [$group, $owner] = eventGroup(0);

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['date' => '2027-03-12', 'end_date' => $endDate]))
        ->assertSessionHas($stored === null ? 'errors' : 'success');

    expect(Event::query()->first()?->end_date->format('Y-m-d'))->toBe($stored);
})->with([
    'missing' => [null, '2027-03-12'],
    'empty' => ['', '2027-03-12'],
    'later day' => ['2027-03-14', '2027-03-14'],
    'before start' => ['2027-03-11', null],
]);

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
        ->and($event->end_date->format('Y-m-d'))->toBe('2027-03-14')
        ->and($event->organizer_id)->toBe($owner->id)
        ->and($event->origin_poll_option_id)->toBe($option->id)
        ->and($event->parent_event_id)->toBe($source->id)
        ->and($event->statusOf($yes))->toBe(ParticipationStatus::Accepted)
        ->and($event->statusOf($maybe))->toBe(ParticipationStatus::Pending)
        ->and($event->statusOf($no))->toBeNull()
        ->and($event->statusOf($owner))->toBeNull();
});

it('F-10: a manager creates a sub-event that starts with the parent participants', function () {
    [$group, $owner, , $members] = eventGroup(3);
    $parent = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    EventParticipation::factory()->create(['event_id' => $parent->id, 'user_id' => $owner->id, 'status' => ParticipationStatus::Accepted]);
    EventParticipation::factory()->create(['event_id' => $parent->id, 'user_id' => $members[0]->id, 'status' => ParticipationStatus::Pending]);
    EventParticipation::factory()->create(['event_id' => $parent->id, 'user_id' => $members[1]->id, 'status' => ParticipationStatus::Declined]);
    $organizer = $members[2];
    $group->members()->updateExistingPivot($organizer->id, ['role' => GroupRole::Admin]);

    $this->actingAs($organizer)
        ->get(route('events.create', ['group' => $group, 'parent_event_id' => $parent->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('form.parent_event_id', $parent->id)->where('form.parent_event_title', $parent->title));

    $this->actingAs($organizer)
        ->post(route('events.store', $group), eventInput(['parent_event_id' => $parent->id, 'date' => '2027-03-12', 'end_date' => '2027-03-13']))
        ->assertRedirect()
        ->assertSessionHas('success', __('ui.newEvent.created'));

    $child = Event::query()->where('parent_event_id', $parent->id)->firstOrFail();

    expect($child->end_date->format('Y-m-d'))->toBe('2027-03-13')
        ->and($child->participations->pluck('status', 'user_id')->all())->toBe([
            $owner->id => ParticipationStatus::Pending,
            $members[0]->id => ParticipationStatus::Pending,
            $organizer->id => ParticipationStatus::Accepted,
        ]);
});

it('F-12: the event page offers "Add sub-event" only to managers of an event without a parent', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $parent = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $child = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $parent->id]);

    $this->actingAs($owner)->get(route('events.show', $parent))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_create_sub_event', true));
    $this->actingAs($owner)->get(route('events.show', $child))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_create_sub_event', false));
    $this->actingAs($members[0])->get(route('events.show', $parent))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_create_sub_event', false));
});

it('F-10: a plain member cannot create a sub-event', function () {
    [$group, $owner, , $members] = eventGroup(1);
    $parent = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($members[0])
        ->post(route('events.store', $group), eventInput(['parent_event_id' => $parent->id]))
        ->assertForbidden();
    $this->actingAs($members[0])
        ->get(route('events.create', ['group' => $group, 'parent_event_id' => $parent->id]))
        ->assertForbidden();

    expect(Event::query()->where('parent_event_id', $parent->id)->exists())->toBeFalse();
});

it('F-12: a child event cannot get a sub-event', function () {
    [$group, $owner] = eventGroup(1);
    $parent = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $child = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $parent->id]);

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['parent_event_id' => $child->id]))
        ->assertForbidden();

    expect(Event::query()->where('parent_event_id', $child->id)->exists())->toBeFalse();
});

it('F-10: a parent event from another group is rejected', function () {
    [$group, $owner] = eventGroup(1);
    [$otherGroup, $otherOwner] = eventGroup(0);
    $foreign = Event::factory()->create(['group_id' => $otherGroup->id, 'organizer_id' => $otherOwner->id]);

    $this->actingAs($owner)
        ->post(route('events.store', $group), eventInput(['parent_event_id' => $foreign->id]))
        ->assertSessionHasErrors('parent_event_id');
    $this->actingAs($owner)
        ->get(route('events.create', ['group' => $group, 'parent_event_id' => $foreign->id]))
        ->assertNotFound();

    expect(Event::query()->where('parent_event_id', $foreign->id)->exists())->toBeFalse();
});

<?php

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
 * Create a group with its owner and one plain member.
 *
 * @return array{0: Group, 1: User, 2: User}
 */
function dashboardGroup(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $member];
}

/**
 * Create a poll with the given number of options, answered "yes" on the first few by the user.
 */
function dashboardPoll(Group $group, User $organizer, int $options, ?User $user = null, int $answered = 0, array $attributes = []): Poll
{
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id, ...$attributes]);
    $created = PollOption::factory()->count($options)->create(['poll_id' => $poll->id]);

    foreach ($created->take($answered) as $option) {
        $option->responses()->create(['user_id' => $user->id, 'status' => PollResponseStatus::Yes]);
    }

    return $poll;
}

/**
 * Create an event in the group with the user's participation status.
 */
function dashboardEvent(Group $group, User $organizer, ?User $user = null, ?ParticipationStatus $status = null, array $attributes = []): Event
{
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id, ...$attributes]);

    if ($user !== null && $status !== null) {
        EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => $status]);
    }

    return $event;
}

it('F-22: an open poll answered on two of five options appears under "Waiting for you"', function () {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 5, $member, 2);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('waiting', 1)
        ->where('waiting.0.kind', 'poll')
        ->where('waiting.0.slug', $poll->slug)
        ->where('waiting.0.answered', 2)
        ->where('waiting.0.total', 5)
        ->where('counts.waiting', 1));
});

it('F-22: a completely answered, a closed and a foreign poll are not waiting', function () {
    [$group, $owner, $member] = dashboardGroup();
    dashboardPoll($group, $owner, 2, $member, 2);
    dashboardPoll($group, $owner, 2, $member, 0, ['closed_at' => now()]);
    [$otherGroup, $otherOwner] = dashboardGroup();
    dashboardPoll($otherGroup, $otherOwner, 2);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->where('counts.waiting', 0));
});

it('F-22: an upcoming event with the status "pending" is waiting, a past one is not', function () {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Pending);
    dashboardEvent($group, $owner, $member, ParticipationStatus::Pending, ['date' => now()->subWeek()->format('Y-m-d')]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 1)
        ->where('waiting.0.kind', 'event')
        ->where('waiting.0.slug', $event->slug)
        ->where('waiting.0.group_name', $group->name)
        ->has('upcoming', 0));
});

it('F-22: a pending event under "Waiting for you" carries how many accepted so far', function () {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Pending);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => User::factory()->create()->id]);
    EventParticipation::factory()->declined()->create(['event_id' => $event->id, 'user_id' => User::factory()->create()->id]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('waiting.0.slug', $event->slug)
        ->where('waiting.0.accepted_count', 2));
});

it('F-22: after accepting, the event leaves "Waiting for you" and appears under the upcoming events', function () {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Pending);

    $this->actingAs($member)->put(route('events.participation.update', $event), ['status' => 'accepted']);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('upcoming', 1)
        ->where('upcoming.0.slug', $event->slug)
        ->where('upcoming.0.accepted_count', 1)
        ->where('upcoming.0.accepted_names.0', $member->name)
        ->where('counts.upcoming', 1));
});

it('F-22: upcoming events are ordered by start and leave out declined, unanswered and past events', function () {
    [$group, $owner, $member] = dashboardGroup();
    $later = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => now()->addDays(10)->format('Y-m-d')]);
    $sooner = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => now()->addDays(2)->format('Y-m-d')]);
    $today = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => now()->format('Y-m-d')]);
    dashboardEvent($group, $owner, $member, ParticipationStatus::Declined);
    dashboardEvent($group, $owner);
    dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => now()->subDay()->format('Y-m-d')]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('upcoming', 3)
        ->where('upcoming.0.slug', $today->slug)
        ->where('upcoming.1.slug', $sooner->slug)
        ->where('upcoming.2.slug', $later->slug));
});

it('F-22: an event of a group the person left does not show', function () {
    [$group, $owner, $member] = dashboardGroup();
    dashboardEvent($group, $owner, $member, ParticipationStatus::Pending);
    $group->members()->detach($member);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('upcoming', 0)
        ->has('groups', 0));
});

it('F-22: a follow-up poll is waiting only for accepted participants of the source event', function () {
    [$group, $owner, $member] = dashboardGroup();
    [$pending, $accepted] = User::factory()->count(2)->create()->all();
    $group->addMember($pending, GroupRole::Member, now());
    $group->addMember($accepted, GroupRole::Member, now());
    $event = dashboardEvent($group, $owner, $accepted, ParticipationStatus::Accepted);
    EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $pending->id, 'status' => ParticipationStatus::Pending]);
    $poll = dashboardPoll($group, $owner, 2, null, 0, ['event_id' => $event->id]);

    $this->actingAs($accepted)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 1)
        ->where('waiting.0.slug', $poll->slug)
        ->where('waiting.0.source_event.slug', $event->slug));

    foreach ([$pending, $member] as $person) {
        $this->actingAs($person)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('waiting', fn ($waiting) => collect($waiting)->where('kind', 'poll')->isEmpty()));
    }
});

it('F-22: own claims on upcoming lists show, claims on past lists and of others do not', function () {
    [$group, $owner, $member] = dashboardGroup();
    $upcoming = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'date' => now()->addWeek()->format('Y-m-d')]);
    $past = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'date' => now()->subWeek()->format('Y-m-d')]);
    $salad = RosterItem::factory()->create(['roster_id' => $upcoming->id, 'name' => 'Salad', 'quantity' => 2, 'unit' => 'bowls']);
    $bread = RosterItem::factory()->create(['roster_id' => $past->id, 'name' => 'Bread']);
    $cake = RosterItem::factory()->create(['roster_id' => $upcoming->id, 'name' => 'Cake']);
    RosterItemClaim::factory()->create(['roster_item_id' => $salad->id, 'user_id' => $member->id, 'quantity' => 1]);
    RosterItemClaim::factory()->create(['roster_item_id' => $bread->id, 'user_id' => $member->id]);
    RosterItemClaim::factory()->create(['roster_item_id' => $cake->id, 'user_id' => $owner->id]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('claims', 1)
        ->where('claims.0.item_name', 'Salad')
        ->where('claims.0.quantity', 1)
        ->where('claims.0.unit', 'bowls')
        ->where('claims.0.roster_slug', $upcoming->slug)
        ->where('counts.claims', 1));
});

it('F-22: claims on a list of an event show while the event is upcoming and the person takes part', function () {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
    $pastEvent = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => now()->subWeek()->format('Y-m-d')]);

    foreach ([$event, $pastEvent] as $source) {
        $roster = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $source->id]);
        $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => "Item of {$source->id}"]);
        RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $member->id]);
    }

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('claims', 1)
        ->where('claims.0.item_name', "Item of {$event->id}")
        ->where('claims.0.date', $event->date->format('Y-m-d')));
});

it('F-22: groups show members, visible open polls and upcoming events', function () {
    [$group, $owner, $member] = dashboardGroup();
    dashboardPoll($group, $owner, 1);
    dashboardPoll($group, $owner, 1, null, 0, ['closed_at' => now()]);
    $event = dashboardEvent($group, $owner, $owner, ParticipationStatus::Accepted);
    dashboardEvent($group, $owner, null, null, ['date' => now()->subWeek()->format('Y-m-d')]);
    dashboardPoll($group, $owner, 1, null, 0, ['event_id' => $event->id]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('groups', 1)
        ->where('groups.0.slug', $group->slug)
        ->where('groups.0.role', 'member')
        ->where('groups.0.member_count', 2)
        ->where('groups.0.open_poll_count', 1)
        ->where('groups.0.upcoming_event_count', 1)
        ->where('counts.groups', 1));

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.role', 'owner')
        ->where('groups.0.open_poll_count', 2));
});

it('F-22: standalone lists show with claim progress, past lists and group lists do not', function () {
    [$group, $owner] = dashboardGroup();
    $list = Roster::factory()->create(['owner_id' => $owner->id, 'date' => now()->addWeek()->format('Y-m-d'), 'share_token' => 'token']);
    $taken = RosterItem::factory()->create(['roster_id' => $list->id]);
    RosterItem::factory()->create(['roster_id' => $list->id]);
    RosterItemClaim::factory()->create(['roster_item_id' => $taken->id, 'user_id' => $owner->id]);
    Roster::factory()->create(['owner_id' => $owner->id, 'date' => now()->subWeek()->format('Y-m-d')]);
    Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('lists', 1)
        ->where('lists.0.slug', $list->slug)
        ->where('lists.0.item_count', 2)
        ->where('lists.0.claimed_count', 1)
        ->where('lists.0.is_shared', true)
        ->where('counts.lists', 1));
});

it('F-22: a person without anything gets empty sections and zero counts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('upcoming', 0)
        ->has('claims', 0)
        ->has('groups', 0)
        ->has('lists', 0)
        ->where('counts', ['waiting' => 0, 'upcoming' => 0, 'claims' => 0, 'groups' => 0, 'lists' => 0]));
});

it('F-22: the dashboard needs no additional queries for more groups, polls, events, claims and lists', function () {
    $user = User::factory()->create();
    $queryCount = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $populate = function (int $times) use ($user) {
        foreach (range(1, $times) as $ignored) {
            [$group, $owner] = dashboardGroup();
            $group->addMember($user, GroupRole::Member, now());
            $event = dashboardEvent($group, $owner, $user, ParticipationStatus::Pending);
            dashboardEvent($group, $owner, $user, ParticipationStatus::Accepted);
            dashboardPoll($group, $owner, 3, $user, 1);
            dashboardPoll($group, $owner, 2, null, 0, ['event_id' => $event->id]);
            $roster = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);
            $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
            RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $user->id]);
            $list = Roster::factory()->create(['owner_id' => $user->id]);
            RosterItem::factory()->count(2)->create(['roster_id' => $list->id]);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(4);

    expect($queryCount())->toBe($small);
});

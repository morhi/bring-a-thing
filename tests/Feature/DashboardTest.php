<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Models\EventParticipation;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

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

it('F-22: a person without anything gets empty sections, zero counts and no "Without groups"', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('upcoming', 0)
        ->has('claims', 0)
        ->has('polls', 0)
        ->has('lists', 0)
        ->has('groups', 0)
        ->where('withoutGroup', null)
        ->where('counts', ['waiting' => 0, 'upcoming' => 0, 'claims' => 0, 'polls' => 0, 'lists' => 0, 'groups' => 0]));
});

it('F-22: a closed poll is not under current polls', function () {
    [$group, $owner, $member] = dashboardGroup();
    $open = dashboardPoll($group, $owner, 2);
    dashboardPoll($group, $owner, 2, null, 0, ['closed_at' => now()]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('polls', 1)
        ->where('polls.0.slug', $open->slug)
        ->where('counts.polls', 1));
});

it('F-22: current polls carries the own answer status', function (int $answered, string $status, int $waiting) {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 3, $member, $answered);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('polls', 1)
        ->where('polls.0.slug', $poll->slug)
        ->where('polls.0.status', $status)
        ->where('polls.0.answered', $answered)
        ->where('polls.0.total', 3)
        ->where('polls.0.group_name', $group->name)
        ->has('waiting', $waiting));
})->with([
    'all answered' => [3, 'answered', 0],
    'one of three' => [1, 'partial', 1],
    'none' => [0, 'unanswered', 1],
]);

it('F-22: a poll of a group the person is not in is not under current polls', function () {
    [, , $member] = dashboardGroup();
    [$otherGroup, $otherOwner] = dashboardGroup();
    dashboardPoll($otherGroup, $otherOwner, 2);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('polls', 0));
});

it('F-22: a follow-up poll gets no row under current polls, also for accepted participants', function () {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
    $poll = dashboardPoll($group, $owner, 2, null, 0, ['event_id' => $event->id]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('waiting.0.slug', $poll->slug)
        ->has('polls', 0));
});

it('F-22: a poll reached through a grant shows under current polls for a guest', function () {
    [$group, $owner] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2);
    $guest = User::factory()->create();
    grantOn($poll, $guest);

    $this->actingAs($guest)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('polls', 1)
        ->where('polls.0.slug', $poll->slug)
        ->where('polls.0.status', 'unanswered'));
});

it('F-22: lists shows every active list the person may open', function (Closure $setup, bool $shared) {
    $user = User::factory()->create();
    $roster = $setup($user);
    $roster->forceFill(['share_token' => $shared ? 'token' : null])->save();
    RosterItemClaim::factory()->create(['roster_item_id' => $roster->items()->first()->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('lists', 1)
        ->where('lists.0.slug', $roster->slug)
        ->where('lists.0.item_count', 2)
        ->where('lists.0.claimed_count', 1)
        ->where('lists.0.is_shared', $shared)
        ->where('counts.lists', 1));
})->with([
    'owned standalone, undated' => [fn (User $user) => dashboardList($user), true],
    'owned standalone, future date' => [fn (User $user) => dashboardList($user, ['date' => now()->addWeek()->format('Y-m-d')]), false],
    'standalone of someone else with an own claim' => [fn (User $user) => dashboardList(User::factory()->create()), false],
    'list of an own group' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());

        return dashboardList($owner, ['group_id' => $group->id]);
    }, false],
    'list of an upcoming event the person takes part in' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());
        $event = dashboardEvent($group, $owner, $user, ParticipationStatus::Accepted);

        return dashboardList($owner, ['event_id' => $event->id]);
    }, false],
]);

it('F-22: lists leaves out a list whose date is past', function (int $days, int $count) {
    $user = User::factory()->create();
    dashboardList($user, ['date' => now()->addDays($days)->format('Y-m-d')]);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('lists', $count));
})->with([
    'today' => [0, 1],
    'yesterday' => [-1, 0],
]);

it('F-22: lists leaves out the list of an event that is past', function () {
    [$group, $owner, $member] = dashboardGroup();
    $upcoming = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
    $past = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => now()->subWeek()->format('Y-m-d')]);
    $list = dashboardList($owner, ['event_id' => $upcoming->id]);
    dashboardList($owner, ['event_id' => $past->id]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('lists', 1)
        ->where('lists.0.slug', $list->slug)
        ->where('lists.0.event_title', $upcoming->title));
});

it('F-22: lists leaves out lists the person may not open', function (Closure $setup) {
    $user = User::factory()->create();
    $setup($user);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('lists', 0));
})->with([
    'list of a foreign group' => [function () {
        [$group, $owner] = dashboardGroup();
        dashboardList($owner, ['group_id' => $group->id]);
    }],
    'event list without own participation' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());
        dashboardList($owner, ['event_id' => dashboardEvent($group, $owner)->id]);
    }],
    'foreign standalone list without own claim' => [fn () => dashboardList(User::factory()->create())],
]);

it('F-22: lists includes the list of an event the guest reaches through a grant', function () {
    [$group, $owner] = dashboardGroup();
    $event = dashboardEvent($group, $owner);
    $list = dashboardList($owner, ['event_id' => $event->id]);
    [$participant, $bystander] = User::factory()->count(2)->create()->all();
    grantOn($event, $participant);
    grantOn($event, $bystander);
    EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $participant->id, 'status' => ParticipationStatus::Accepted]);

    $this->actingAs($participant)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('lists', 1)
        ->where('lists.0.slug', $list->slug));
    $this->actingAs($bystander)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('lists', 0));
});

it('F-22: withoutGroup carries counts of grant-reached events, polls and groupless lists', function (int $events, int $polls, int $lists) {
    $guest = User::factory()->create();
    [$group, $owner] = dashboardGroup();

    Collection::times($events, fn () => grantOn(dashboardEvent($group, $owner), $guest));
    Collection::times($polls, fn () => grantOn(dashboardPoll($group, $owner, 1), $guest));
    Collection::times($lists, fn () => dashboardList($guest));

    $this->actingAs($guest)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('withoutGroup', ['events_count' => $events, 'polls_count' => $polls, 'lists_count' => $lists])
        ->has('groups', 0)
        ->where('counts.groups', 0));
})->with([
    'only an event grant' => [1, 0, 0],
    'only a poll grant' => [0, 1, 0],
    'only an own groupless list' => [0, 0, 1],
    'all three, two events' => [2, 1, 1],
]);

it('F-22: withoutGroup does not count items of the person\'s own groups', function (bool $foreignGrant, ?array $expected) {
    [$group, $owner, $member] = dashboardGroup();
    grantOn(dashboardEvent($group, $owner), $member);
    grantOn(dashboardPoll($group, $owner, 1), $member);
    dashboardList($member, ['group_id' => $group->id]);

    if ($foreignGrant) {
        [$otherGroup, $otherOwner] = dashboardGroup();
        grantOn(dashboardEvent($otherGroup, $otherOwner), $member);
    }

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('withoutGroup', $expected)
        ->has('groups', 1));
})->with([
    'only own group items' => [false, null],
    'plus a foreign event grant' => [true, ['events_count' => 1, 'polls_count' => 0, 'lists_count' => 0]],
]);

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
            dashboardList($user, [], $user);
            dashboardList($owner, ['group_id' => $group->id]);
            [$foreignGroup, $foreignOwner] = dashboardGroup();
            $grantEvent = dashboardEvent($foreignGroup, $foreignOwner, $user, ParticipationStatus::Accepted);
            dashboardList($foreignOwner, ['event_id' => $grantEvent->id]);
            grantOn($grantEvent, $user);
            grantOn(dashboardPoll($foreignGroup, $foreignOwner, 2, $user, 1), $user);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(4);

    expect($queryCount())->toBe($small);
});

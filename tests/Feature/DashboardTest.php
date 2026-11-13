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
        ->where('waiting.0.total', 5));
});

it('F-22: a completely answered, a closed and a foreign poll are not waiting', function () {
    [$group, $owner, $member] = dashboardGroup();
    dashboardPoll($group, $owner, 2, $member, 2);
    dashboardPoll($group, $owner, 2, $member, 0, ['closed_at' => now()]);
    [$otherGroup, $otherOwner] = dashboardGroup();
    dashboardPoll($otherGroup, $otherOwner, 2);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0));
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
        ->where('upcoming.0.accepted_names.0', $member->name));
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
        ->where('claims.0.roster_slug', $upcoming->slug));
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
        ->where('groups.0.upcoming_event_count', 1));

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.role', 'owner')
        ->where('groups.0.open_poll_count', 2));
});

it('F-22: a person without anything gets empty sections and no "Without groups"', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('upcoming', 0)
        ->has('claims', 0)
        ->has('polls', 0)
        ->has('lists', 0)
        ->has('groups', 0)
        ->where('withoutGroup', null));
});

it('F-22: a closed poll is not under current polls', function () {
    [$group, $owner, $member] = dashboardGroup();
    $open = dashboardPoll($group, $owner, 2);
    dashboardPoll($group, $owner, 2, null, 0, ['closed_at' => now()]);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('polls', 1)
        ->where('polls.0.slug', $open->slug));
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
        ->where('lists.0.is_shared', $shared));
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

/**
 * Collect the group cards of the user's dashboard, keyed by slug.
 *
 * @return Collection<string, array<string, mixed>>
 */
function dashboardCards(User $user): Collection
{
    return collect(test()->actingAs($user)->get(route('dashboard'))->assertOk()->inertiaProps('groups'))->keyBy('slug');
}

/**
 * Reduce a guest card to its three counts.
 *
 * @param  array<string, mixed>  $card
 * @return array{polls: int, events: int, lists: int}
 */
function guestCounts(array $card): array
{
    return ['polls' => $card['open_poll_count'], 'events' => $card['upcoming_event_count'], 'lists' => $card['active_list_count']];
}

it('F-22: a person holding a grant shows the group as a guest group', function (Closure $grant) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $grant($group, $owner, $guest);

    $this->actingAs($guest)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('groups', 1)
        ->where('groups.0.slug', $group->slug)
        ->where('groups.0.name', $group->name)
        ->where('groups.0.is_guest', true)
        ->where('groups.0.role', null)
        ->where('groups.0.member_count', null));
})->with([
    'event link' => [fn ($group, User $owner, User $guest) => grantOn(dashboardEvent($group, $owner), $guest)],
    'poll link' => [fn ($group, User $owner, User $guest) => grantOn(dashboardPoll($group, $owner, 1), $guest)],
]);

it('F-22: a guest group counts only accessible items', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    grantOn(dashboardEvent($group, $owner), $guest);
    dashboardEvent($group, $owner);
    dashboardPoll($group, $owner, 1);
    dashboardList($owner, ['group_id' => $group->id]);

    expect(guestCounts(dashboardCards($guest)[$group->slug]))->toBe(['polls' => 0, 'events' => 1, 'lists' => 0]);
});

it('F-22: guest counts follow the access rules of each item type', function (Closure $setup, array $expected) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $setup($group, $owner, $guest);

    expect(guestCounts(dashboardCards($guest)[$group->slug]))->toBe($expected);
})->with([
    'follow-up poll, source event accepted' => [function ($group, User $owner, User $guest) {
        $event = dashboardEvent($group, $owner, $guest, ParticipationStatus::Accepted);
        grantOn($event, $guest);
        dashboardPoll($group, $owner, 1, null, 0, ['event_id' => $event->id]);
    }, ['polls' => 1, 'events' => 1, 'lists' => 0]],
    'follow-up poll, source event pending' => [function ($group, User $owner, User $guest) {
        $event = dashboardEvent($group, $owner, $guest, ParticipationStatus::Pending);
        grantOn($event, $guest);
        dashboardPoll($group, $owner, 1, null, 0, ['event_id' => $event->id]);
    }, ['polls' => 0, 'events' => 1, 'lists' => 0]],
    'list of the granted event, accepted' => [function ($group, User $owner, User $guest) {
        $event = dashboardEvent($group, $owner, $guest, ParticipationStatus::Accepted);
        grantOn($event, $guest);
        dashboardList($owner, ['event_id' => $event->id]);
    }, ['polls' => 0, 'events' => 1, 'lists' => 1]],
    'list of the granted event, no participation' => [function ($group, User $owner, User $guest) {
        $event = dashboardEvent($group, $owner);
        grantOn($event, $guest);
        dashboardList($owner, ['event_id' => $event->id]);
    }, ['polls' => 0, 'events' => 1, 'lists' => 0]],
    'closed poll under a poll grant' => [fn ($group, User $owner, User $guest) => grantOn(dashboardPoll($group, $owner, 1, null, 0, ['closed_at' => now()]), $guest),
        ['polls' => 0, 'events' => 0, 'lists' => 0]],
    'past event under an event grant' => [fn ($group, User $owner, User $guest) => grantOn(dashboardEvent($group, $owner, null, null, ['date' => now()->subWeek()->format('Y-m-d')]), $guest),
        ['polls' => 0, 'events' => 0, 'lists' => 0]],
]);

it('F-22: several grants in one group give one card and count items once', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = dashboardEvent($group, $owner);
    $poll = dashboardPoll($group, $owner, 1);
    $fixed = dashboardEvent($group, $owner, null, null, ['origin_poll_option_id' => $poll->options()->first()->id]);
    dashboardEvent($group, $owner, null, null, ['parent_event_id' => $event->id]);
    grantOn($event, $guest);
    grantOn($poll, $guest);
    grantOn($fixed, $guest);

    $cards = dashboardCards($guest);

    expect($cards)->toHaveCount(1)
        ->and(guestCounts($cards[$group->slug]))->toBe(['polls' => 1, 'events' => 3, 'lists' => 0]);
});

it('F-22: a member group stays a member group, also with a grant inside it', function () {
    [$memberGroup, $memberOwner, $person] = dashboardGroup();
    grantOn(dashboardEvent($memberGroup, $memberOwner), $person);
    [$guestGroup, $guestOwner] = dashboardGroup();
    grantOn(dashboardEvent($guestGroup, $guestOwner), $person);

    $cards = dashboardCards($person);

    expect($cards)->toHaveCount(2)
        ->and($cards[$memberGroup->slug])->toMatchArray(['is_guest' => false, 'role' => 'member', 'member_count' => 2])
        ->and($cards[$guestGroup->slug]['is_guest'])->toBeTrue();
});

it('F-22: the list count covers group and event lists the same way for members and guests', function () {
    [$group, $owner, $member] = dashboardGroup();
    dashboardList($owner, ['group_id' => $group->id]);
    dashboardList($owner, ['group_id' => $group->id, 'date' => now()->subWeek()->format('Y-m-d')]);
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
    dashboardList($owner, ['event_id' => $event->id]);
    $guest = User::factory()->create();
    grantOn($event, $guest);
    EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $guest->id, 'status' => ParticipationStatus::Accepted]);

    expect(dashboardCards($member)[$group->slug]['active_list_count'])->toBe(2)
        ->and(dashboardCards($guest)[$group->slug]['active_list_count'])->toBe(1);

    $group->addMember($guest, GroupRole::Member, now());

    expect(dashboardCards($guest)[$group->slug]['active_list_count'])->toBe(2);
});

it('F-22: after the person becomes a member the guest mark is gone', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    grantOn(dashboardEvent($group, $owner), $guest);

    expect(dashboardCards($guest)[$group->slug]['is_guest'])->toBeTrue();

    $group->addMember($guest, GroupRole::Member, now());
    $cards = dashboardCards($guest);

    expect($cards)->toHaveCount(1)
        ->and($cards[$group->slug])->toMatchArray(['is_guest' => false, 'role' => 'member', 'member_count' => 3]);
});

it('F-22: a revoked grant or a grant of another person gives no guest group', function (Closure $setup) {
    [$group, $owner] = dashboardGroup();
    $person = User::factory()->create();
    $setup(dashboardEvent($group, $owner), $person);

    $this->actingAs($person)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('groups', 0));
})->with([
    'grant deleted after creation' => [function ($event, User $person) {
        grantOn($event, $person);
        $event->accessGrants()->where('user_id', $person->id)->delete();
    }],
    'grant held by another user' => [fn ($event) => grantOn($event, User::factory()->create())],
]);

it('F-22: the card "Without groups" depends on own groupless lists only', function (Closure $setup, ?array $expected) {
    [$group, $owner] = dashboardGroup();
    $person = User::factory()->create();
    $setup($group, $owner, $person);

    $this->actingAs($person)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('withoutGroup', $expected));
})->with([
    'own groupless list only' => [fn ($group, User $owner, User $person) => dashboardList($person), ['lists_count' => 1]],
    'grants only' => [function ($group, User $owner, User $person) {
        grantOn(dashboardEvent($group, $owner), $person);
        grantOn(dashboardPoll($group, $owner, 1), $person);
    }, null],
    'own list plus grants' => [function ($group, User $owner, User $person) {
        dashboardList($person);
        grantOn(dashboardEvent($group, $owner), $person);
        grantOn(dashboardPoll($group, $owner, 1), $person);
    }, ['lists_count' => 1]],
    'foreign groupless list with only a claim' => [fn ($group, User $owner, User $person) => dashboardList($owner, [], $person), null],
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

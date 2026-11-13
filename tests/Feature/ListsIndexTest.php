<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Collect the slugs of the lists the page shows for the user with the given filters.
 *
 * @return array<int, string>
 */
function listsIndexSlugs(User $user, array $query = []): array
{
    $lists = test()->actingAs($user)->get(route('lists.index', $query))->assertOk()->inertiaProps('lists');

    return collect($lists)->pluck('slug')->all();
}

it('F-28: a guest is sent to the login', function () {
    $this->get(route('lists.index'))->assertRedirect(route('login'));
});

it('F-28: a person without lists gets the empty list', function () {
    $this->actingAs(User::factory()->create())->get(route('lists.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Rosters/Index')
        ->has('lists', 0)
        ->where('filters', ['mine' => false, 'tasks' => false, 'all' => false]));
});

it('F-28: the page shows the lists the person may open', function (Closure $setup) {
    $user = User::factory()->create();
    $roster = $setup($user);

    expect(listsIndexSlugs($user))->toBe([$roster->slug]);
})->with([
    'owned standalone' => [fn (User $user) => dashboardList($user)],
    'claimed standalone' => [fn (User $user) => dashboardList(User::factory()->create(), [], $user)],
    'own group list' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());

        return dashboardList($owner, ['group_id' => $group->id]);
    }],
    'event list as participant' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());

        return dashboardList($owner, ['event_id' => dashboardEvent($group, $owner, $user, ParticipationStatus::Pending)->id]);
    }],
]);

it('F-28: lists the person may not open never appear', function (Closure $setup) {
    $user = User::factory()->create();
    $setup($user);

    expect(listsIndexSlugs($user, ['all' => 1]))->toBe([]);
})->with([
    'foreign group list' => [function () {
        [$group, $owner] = dashboardGroup();
        dashboardList($owner, ['group_id' => $group->id]);
    }],
    'event list without participation, or declined, with a claim' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());
        dashboardList($owner, ['event_id' => dashboardEvent($group, $owner)->id], $user);
        dashboardList($owner, ['event_id' => dashboardEvent($group, $owner, $user, ParticipationStatus::Declined)->id], $user);
    }],
    'foreign standalone list without claim' => [fn () => dashboardList(User::factory()->create())],
]);

it('F-28: active shows undated and future lists, all adds past ones', function (Closure $setup, bool $active) {
    $user = User::factory()->create();
    $roster = $setup($user);

    expect(listsIndexSlugs($user))->toBe($active ? [$roster->slug] : [])
        ->and(listsIndexSlugs($user, ['all' => 1]))->toBe([$roster->slug]);
})->with([
    'undated' => [fn (User $user) => dashboardList($user), true],
    'today' => [fn (User $user) => dashboardList($user, ['date' => now()->format('Y-m-d')]), true],
    'yesterday' => [fn (User $user) => dashboardList($user, ['date' => now()->subDay()->format('Y-m-d')]), false],
    'list of a past event' => [function (User $user) {
        [$group, $owner] = dashboardGroup();
        $group->addMember($user, GroupRole::Member, now());
        $event = dashboardEvent($group, $owner, $user, ParticipationStatus::Accepted, ['date' => now()->subWeek()->format('Y-m-d')]);

        return dashboardList($owner, ['event_id' => $event->id]);
    }, false],
]);

it('F-28: mine keeps owned lists and lists with an own claim', function (Closure $setup, bool $kept) {
    [$group, $owner, $member] = dashboardGroup();
    $roster = $setup($member, $group, $owner);

    expect(listsIndexSlugs($member))->toBe([$roster->slug])
        ->and(listsIndexSlugs($member, ['mine' => 1]))->toBe($kept ? [$roster->slug] : []);
})->with([
    'owned standalone' => [fn (User $member) => dashboardList($member), true],
    'owned group list' => [fn (User $member, $group) => dashboardList($member, ['group_id' => $group->id]), true],
    'other\'s list with own claim' => [fn (User $member, $group, User $owner) => dashboardList($owner, ['group_id' => $group->id], $member), true],
    'visible group list neither owned nor claimed' => [fn (User $member, $group, User $owner) => dashboardList($owner, ['group_id' => $group->id], $owner), false],
]);

it('F-28: tasks only keeps lists with at least one task', function (array $quantities, bool $kept) {
    $user = User::factory()->create();
    $roster = dashboardList($user)->load('items');
    $roster->items->each(fn (RosterItem $item, int $index) => $item->forceFill(['quantity' => $quantities[$index]])->save());

    expect(listsIndexSlugs($user, ['tasks' => 1]))->toBe($kept ? [$roster->slug] : []);
})->with([
    'only tasks' => [[null, null], true],
    'a task among things' => [[2, null], true],
    'only things' => [[2, 3], false],
]);

it('F-28: filters combine with AND', function () {
    [$group, $owner, $member] = dashboardGroup();
    $match = dashboardList($member, ['date' => now()->subWeek()->format('Y-m-d')]);
    dashboardList($owner, ['group_id' => $group->id, 'date' => now()->subWeek()->format('Y-m-d')]);
    $things = dashboardList($member, ['date' => now()->subWeek()->format('Y-m-d')]);
    $things->items()->update(['quantity' => 2]);
    dashboardList($member, ['date' => now()->subWeek()->format('Y-m-d'), 'group_id' => $group->id])->items()->update(['quantity' => 1]);

    expect(listsIndexSlugs($member, ['mine' => 1, 'tasks' => 1, 'all' => 1]))->toBe([$match->slug])
        ->and(listsIndexSlugs($member, ['mine' => 1, 'tasks' => 1]))->toBe([]);
});

it('F-28: an invalid filter value is rejected', function (array $query, string $key) {
    $this->actingAs(User::factory()->create())
        ->from(route('dashboard'))
        ->get(route('lists.index', $query))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors($key);
})->with([
    'word for a boolean' => [['mine' => 'banana'], 'mine'],
    'array for a boolean' => [['all' => [1]], 'all'],
]);

it('F-28: the page returns the applied filters', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('lists.index', ['mine' => 1, 'all' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['mine' => true, 'tasks' => false, 'all' => true]));
});

it('F-28: the page needs no additional queries for more lists', function () {
    $user = User::factory()->create();
    $queryCount = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('lists.index', ['all' => 1]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $populate = function (int $times) use ($user) {
        foreach (range(1, $times) as $ignored) {
            [$group, $owner] = dashboardGroup();
            $group->addMember($user, GroupRole::Member, now());
            $event = dashboardEvent($group, $owner, $user, ParticipationStatus::Accepted);
            dashboardList($owner, ['event_id' => $event->id], $user);
            dashboardList($owner, ['group_id' => $group->id]);
            $roster = dashboardList($user, ['date' => now()->subWeek()->format('Y-m-d')]);
            RosterItemClaim::factory()->create(['roster_item_id' => $roster->items()->first()->id, 'user_id' => $owner->id]);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(4);

    expect($queryCount())->toBe($small);
});

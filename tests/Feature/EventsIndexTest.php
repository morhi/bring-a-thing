<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Collect the slugs of the events the page shows for the user with the given filters.
 *
 * @return array<int, string>
 */
function eventsIndexSlugs(User $user, array $query = []): array
{
    $events = test()->actingAs($user)->get(route('events.index', $query))->assertOk()->inertiaProps('events');

    return collect($events)->pluck('slug')->all();
}

it('F-32: a guest is sent to the login', function () {
    $this->get(route('events.index'))->assertRedirect(route('login'));
});

it('F-32: a person without events gets the empty list', function () {
    $this->actingAs(User::factory()->create())->get(route('events.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Events/Index')
        ->has('events', 0)
        ->where('filters', ['mine' => false, 'all' => false]));
});

it('F-32: an event of a foreign group never appears', function () {
    $user = User::factory()->create();
    [$group, $owner] = dashboardGroup();
    dashboardEvent($group, $owner);

    expect(eventsIndexSlugs($user, ['all' => 1]))->toBe([]);
});

it('F-32: the page shows accepted and pending events of the person\'s groups', function (ParticipationStatus $status) {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, $status);

    expect(eventsIndexSlugs($member))->toBe([$event->slug]);
})->with([
    'accepted' => ParticipationStatus::Accepted,
    'pending' => ParticipationStatus::Pending,
]);

it('F-32: declined, withdrawn and child events do not appear', function (Closure $setup) {
    [$group, $owner, $member] = dashboardGroup();
    $setup($group, $owner, $member);

    expect(eventsIndexSlugs($member, ['all' => 1]))->toBe([]);
})->with([
    'declined' => [fn ($group, $owner, $member) => dashboardEvent($group, $owner, $member, ParticipationStatus::Declined)],
    'withdrawn, no participation left' => [fn ($group, $owner) => dashboardEvent($group, $owner)],
    'child event' => [function ($group, $owner, $member) {
        $parent = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
        dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['parent_event_id' => $parent->id]);
        $parent->participations()->delete();
    }],
]);

it('F-32: upcoming shows undated and future events, all adds past ones', function (string $date, bool $upcoming) {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => $date, 'starts_at' => null]);

    expect(eventsIndexSlugs($member))->toBe($upcoming ? [$event->slug] : [])
        ->and(eventsIndexSlugs($member, ['all' => 1]))->toBe([$event->slug]);
})->with([
    'today' => [fn () => now()->format('Y-m-d'), true],
    'next week' => [fn () => now()->addWeek()->format('Y-m-d'), true],
    'yesterday' => [fn () => now()->subDay()->format('Y-m-d'), false],
]);

it('F-32: mine keeps accepted events and hides pending ones', function (ParticipationStatus $status, bool $kept) {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, $status);

    expect(eventsIndexSlugs($member, ['mine' => 1]))->toBe($kept ? [$event->slug] : []);
})->with([
    'accepted' => [ParticipationStatus::Accepted, true],
    'pending' => [ParticipationStatus::Pending, false],
]);

it('F-32: filters combine with AND', function () {
    [$group, $owner, $member] = dashboardGroup();
    $past = now()->subWeek()->format('Y-m-d');
    $match = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['date' => $past]);
    dashboardEvent($group, $owner, $member, ParticipationStatus::Pending, ['date' => $past]);
    dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);

    expect(eventsIndexSlugs($member, ['mine' => 1, 'all' => 1]))->toContain($match->slug)->toHaveCount(2)
        ->and(eventsIndexSlugs($member, ['mine' => 1]))->toHaveCount(1)->not->toContain($match->slug);
});

it('F-32: an invalid filter value is rejected', function (array $query, string $key) {
    $this->actingAs(User::factory()->create())
        ->from(route('dashboard'))
        ->get(route('events.index', $query))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors($key);
})->with([
    'word for a boolean' => [['mine' => 'banana'], 'mine'],
    'array for a boolean' => [['all' => [1]], 'all'],
]);

it('F-32: the page needs no additional queries for more events', function () {
    $user = User::factory()->create();
    $queryCount = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('events.index', ['all' => 1]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $populate = function (int $times) use ($user) {
        foreach (range(1, $times) as $ignored) {
            [$group, $owner] = dashboardGroup();
            $group->addMember($user, GroupRole::Member, now());
            dashboardEvent($group, $owner, $user, ParticipationStatus::Accepted);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(4);

    expect($queryCount())->toBe($small);
});

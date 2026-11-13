<?php

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Collect the slugs per section of the page "Without groups" for the user.
 *
 * @return array{events: array<int, string>, polls: array<int, string>, lists: array<int, string>}
 */
function withoutGroupSlugs(User $user): array
{
    $response = test()->actingAs($user)->get(route('without-group.show'))->assertOk();

    return collect(['events', 'polls', 'lists'])
        ->mapWithKeys(fn (string $section) => [$section => collect($response->inertiaProps($section))->pluck('slug')->all()])
        ->all();
}

it('F-29: a guest is sent to the login', function () {
    $this->get(route('without-group.show'))->assertRedirect(route('login'));
});

it('F-29: a person without grants sees the empty state', function () {
    $this->actingAs(User::factory()->create())->get(route('without-group.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('WithoutGroup/Show')
        ->has('events', 0)
        ->has('polls', 0)
        ->has('lists', 0));
});

it('F-29: the page lists grant-reached events and polls and own groupless lists', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = dashboardEvent($group, $owner);
    $poll = dashboardPoll($group, $owner, 2, $guest, 1);
    grantOn($event, $guest);
    grantOn($poll, $guest);
    $list = dashboardList($guest);

    $this->actingAs($guest)->get(route('without-group.show'))->assertInertia(fn (Assert $page) => $page
        ->has('events', 1)
        ->where('events.0.slug', $event->slug)
        ->has('polls', 1)
        ->where('polls.0.slug', $poll->slug)
        ->where('polls.0.status', 'partial')
        ->where('polls.0.group_name', $group->name)
        ->has('lists', 1)
        ->where('lists.0.slug', $list->slug));
});

it('F-29: an event is reached through its own grant, a child of a granted event or an event fixed from a granted poll', function (Closure $setup) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = $setup($group, $owner, $guest);

    expect(withoutGroupSlugs($guest)['events'])->toContain($event->slug);
})->with([
    'own grant' => [function ($group, User $owner, User $guest) {
        $event = dashboardEvent($group, $owner);
        grantOn($event, $guest);

        return $event;
    }],
    'child of a granted event' => [function ($group, User $owner, User $guest) {
        $parent = dashboardEvent($group, $owner);
        grantOn($parent, $guest);

        return dashboardEvent($group, $owner, null, null, ['parent_event_id' => $parent->id]);
    }],
    'fixed from a granted poll' => [function ($group, User $owner, User $guest) {
        $poll = dashboardPoll($group, $owner, 1);
        grantOn($poll, $guest);

        return dashboardEvent($group, $owner, null, null, ['origin_poll_option_id' => $poll->options()->first()->id]);
    }],
]);

it('F-29: a poll is reached through its grant, a follow-up poll only when the source event is accepted', function (?ParticipationStatus $status, bool $followUp) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();

    if ($status === null) {
        $poll = dashboardPoll($group, $owner, 1);
        grantOn($poll, $guest);
    } else {
        $event = dashboardEvent($group, $owner, $guest, $status);
        grantOn($event, $guest);
        $poll = dashboardPoll($group, $owner, 1, null, 0, ['event_id' => $event->id]);
    }

    expect(withoutGroupSlugs($guest)['polls'])->toBe($followUp ? [$poll->slug] : []);
})->with([
    'direct poll grant' => [null, true],
    'follow-up, source event accepted' => [ParticipationStatus::Accepted, true],
    'follow-up, source event pending' => [ParticipationStatus::Pending, false],
]);

it('F-29: groupless lists shows the owned list, not others', function (Closure $setup, bool $shown) {
    [$group, $owner, $member] = dashboardGroup();
    $roster = $setup($member, $group, $owner);

    expect(withoutGroupSlugs($member)['lists'])->toBe($shown ? [$roster->slug] : []);
})->with([
    'owned groupless' => [fn (User $member) => dashboardList($member), true],
    'groupless of someone else with own claim' => [fn (User $member) => dashboardList(User::factory()->create(), [], $member), true],
    'owned group list' => [fn (User $member, $group) => dashboardList($member, ['group_id' => $group->id]), false],
    'owned event list' => [fn (User $member, $group, User $owner) => dashboardList($member, ['event_id' => dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted)->id]), false],
    'foreign groupless without claim' => [fn () => dashboardList(User::factory()->create()), false],
]);

it('F-29: items of the person\'s own groups are not on the page', function () {
    [$group, $owner, $member] = dashboardGroup();
    grantOn(dashboardEvent($group, $owner), $member);
    grantOn(dashboardPoll($group, $owner, 1), $member);

    expect(withoutGroupSlugs($member))->toBe(['events' => [], 'polls' => [], 'lists' => []]);
});

it('F-29: a revoked or foreign grant keeps the item out', function (Closure $setup) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $hidden = $setup($group, $owner, $guest);

    expect(withoutGroupSlugs($guest)['events'])->not->toContain($hidden->slug);
})->with([
    'grant deleted after creation' => [function ($group, User $owner, User $guest) {
        $event = dashboardEvent($group, $owner);
        grantOn($event, $guest);
        $event->accessGrants()->where('user_id', $guest->id)->delete();

        return $event;
    }],
    'grant held by another user' => [function ($group, User $owner) {
        $event = dashboardEvent($group, $owner);
        grantOn($event, User::factory()->create());

        return $event;
    }],
    'sibling of a granted event' => [function ($group, User $owner, User $guest) {
        grantOn(dashboardEvent($group, $owner), $guest);

        return dashboardEvent($group, $owner);
    }],
    'event of another group without grant' => [function () {
        [$otherGroup, $otherOwner] = dashboardGroup();

        return dashboardEvent($otherGroup, $otherOwner);
    }],
]);

it('F-29: a guest who joined through the invite link sees the shared event here', function () {
    [$group, $owner] = dashboardGroup();
    $event = dashboardEvent($group, $owner);

    $this->post(route('events.join.store', $event->enableInviteLink()), ['name' => 'Link Guest'])->assertRedirect(route('events.show', $event));

    $this->get(route('without-group.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('events', 1)
        ->where('events.0.slug', $event->slug));
});

it('F-29: the page needs no additional queries for more grants and lists', function () {
    $user = User::factory()->create();
    $queryCount = function () use ($user) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('without-group.show'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $populate = function (int $times) use ($user) {
        foreach (range(1, $times) as $ignored) {
            [$group, $owner] = dashboardGroup();
            $event = dashboardEvent($group, $owner, $user, ParticipationStatus::Accepted);
            grantOn($event, $user);
            Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $event->id]);
            dashboardPoll($group, $owner, 2, $user, 1, ['event_id' => $event->id]);
            grantOn(dashboardPoll($group, $owner, 3, $user, 2), $user);
            dashboardList($user, [], $user);
            dashboardList($owner, [], $user);
            EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $owner->id, 'status' => ParticipationStatus::Accepted]);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(3);

    expect($queryCount())->toBe($small);
});

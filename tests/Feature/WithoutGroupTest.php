<?php

use App\Enums\ParticipationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Collect the list slugs of the page "Without groups" for the user.
 *
 * @return array<int, string>
 */
function withoutGroupSlugs(User $user): array
{
    $response = test()->actingAs($user)->get(route('without-group.show'))->assertOk();

    return collect($response->inertiaProps('lists'))->pluck('slug')->all();
}

it('F-29: a guest is sent to the login', function () {
    $this->get(route('without-group.show'))->assertRedirect(route('login'));
});

it('F-29: a person without own groupless lists sees the empty state', function () {
    $this->actingAs(User::factory()->create())->get(route('without-group.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('WithoutGroup/Show')
        ->has('lists', 0)
        ->missing('events')
        ->missing('polls'));
});

it('F-29: Without groups holds only own groupless lists', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = dashboardEvent($group, $owner, null, null, ['title' => 'Granted event title']);
    $child = dashboardEvent($group, $owner, null, null, ['title' => 'Child event title', 'parent_event_id' => $event->id]);
    $poll = dashboardPoll($group, $owner, 2, null, 0, ['title' => 'Granted poll title']);
    grantOn($event, $guest);
    grantOn($poll, $guest);
    $list = dashboardList($guest);

    $response = $this->actingAs($guest)->get(route('without-group.show'))->assertInertia(fn (Assert $page) => $page
        ->has('lists', 1)
        ->where('lists.0.slug', $list->slug));
    $props = json_encode($response->inertiaProps());

    foreach ([$event, $child, $poll] as $item) {
        expect($props)->not->toContain($item->slug)->not->toContain($item->title);
    }
});

it('F-29: groupless lists shows the owned list, not others', function (Closure $setup, bool $shown) {
    [$group, $owner, $member] = dashboardGroup();
    $roster = $setup($member, $group, $owner);

    expect(withoutGroupSlugs($member))->toBe($shown ? [$roster->slug] : []);
})->with([
    'owned groupless' => [fn (User $member) => dashboardList($member), true],
    'groupless of someone else with own claim' => [fn (User $member) => dashboardList(User::factory()->create(), [], $member), false],
    'owned group list' => [fn (User $member, $group) => dashboardList($member, ['group_id' => $group->id]), false],
    'owned event list' => [fn (User $member, $group, User $owner) => dashboardList($member, ['event_id' => dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted)->id]), false],
    'foreign groupless without claim' => [fn () => dashboardList(User::factory()->create()), false],
]);

it('F-29: items of the person\'s own groups are not on the page', function () {
    [$group, $owner, $member] = dashboardGroup();
    grantOn(dashboardEvent($group, $owner), $member);
    grantOn(dashboardPoll($group, $owner, 1), $member);
    dashboardList($member, ['group_id' => $group->id]);

    expect(withoutGroupSlugs($member))->toBe([]);
});

it('F-26/F-27: joining through the invite link ends in a guest group', function (Closure $create, string $joinRoute, string $section) {
    [$group, $owner] = dashboardGroup();
    $item = $create($group, $owner);

    $this->post(route($joinRoute, $item->enableInviteLink()), ['name' => 'Link Guest'])->assertRedirect();

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('groups', 1)
        ->where('groups.0.slug', $group->slug)
        ->where('groups.0.is_guest', true)
        ->where('withoutGroup', null));
    $this->get(route('groups.show', $group))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('page.is_guest', true)
        ->has("page.{$section}", 1)
        ->where("page.{$section}.0.slug", $item->slug));
    $this->get(route('without-group.show'))->assertInertia(fn (Assert $page) => $page->has('lists', 0));
})->with([
    'event link' => [fn ($group, User $owner) => dashboardEvent($group, $owner), 'events.join.store', 'events'],
    'poll link' => [fn ($group, User $owner) => dashboardPoll($group, $owner, 1), 'polls.join.store', 'polls'],
]);

it('F-29: the page needs no additional queries for more lists', function () {
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
            dashboardList($user, [], $user);
            dashboardList($user);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(3);

    expect($queryCount())->toBe($small);
});

<?php

use App\Enums\ParticipationStatus;
use App\Models\Group;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Collect the slugs of the polls the page shows for the user with the given filters.
 *
 * @return array<int, string>
 */
function pollsIndexSlugs(User $user, array $query = []): array
{
    $polls = test()->actingAs($user)->get(route('polls.index', $query))->assertOk()->inertiaProps('polls');

    return collect($polls)->pluck('slug')->all();
}

it('F-31: a guest is sent to the login', function () {
    $this->get(route('polls.index'))->assertRedirect(route('login'));
});

it('F-31: a person without polls gets the empty list', function () {
    $this->actingAs(User::factory()->create())->get(route('polls.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Polls/Index')
        ->has('polls', 0)
        ->where('filters', ['unanswered' => false, 'mine' => false, 'all' => false]));
});

it('F-31: the page shows the polls the person may open', function () {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2);

    expect(pollsIndexSlugs($member))->toBe([$poll->slug]);
});

it('F-31: polls the person may not open never appear', function (Closure $setup) {
    [$group, $owner, $member] = dashboardGroup();
    $setup($member, $group, $owner);

    expect(pollsIndexSlugs($member, ['all' => 1]))->toBe([]);
})->with([
    'foreign group' => [function () {
        [$group, $owner] = dashboardGroup();
        dashboardPoll($group, $owner, 2);
    }],
    'withdrawn' => [function (User $member, Group $group, User $owner) {
        dashboardPoll($group, $owner, 2)->withdrawals()->create(['user_id' => $member->id]);
    }],
    'follow-up poll of an event without acceptance' => [function (User $member, Group $group, User $owner) {
        dashboardPoll($group, $owner, 2, attributes: ['event_id' => dashboardEvent($group, $owner, $member, ParticipationStatus::Pending)->id]);
    }],
]);

it('F-31: a follow-up poll appears for an accepted participant of its event', function () {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2, attributes: ['event_id' => dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted)->id]);

    expect(pollsIndexSlugs($member))->toBe([$poll->slug]);
});

it('F-31: open shows open polls, all adds closed ones', function (array $attributes, bool $open) {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2, attributes: $attributes);

    expect(pollsIndexSlugs($member))->toBe($open ? [$poll->slug] : [])
        ->and(pollsIndexSlugs($member, ['all' => 1]))->toBe([$poll->slug]);
})->with([
    'open' => [[], true],
    'closed' => [['closed_at' => now()->subDay()], false],
]);

it('F-31: unanswered keeps polls with no or partial answers', function (int $answered, bool $kept) {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2, $member, $answered);

    expect(pollsIndexSlugs($member, ['unanswered' => 1]))->toBe($kept ? [$poll->slug] : []);
})->with([
    'none' => [0, true],
    'partial' => [1, true],
    'complete' => [2, false],
]);

it('F-31: mine keeps polls the person organizes', function (bool $ownPoll) {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $ownPoll ? $member : $owner, 2);

    expect(pollsIndexSlugs($member))->toBe([$poll->slug])
        ->and(pollsIndexSlugs($member, ['mine' => 1]))->toBe($ownPoll ? [$poll->slug] : []);
})->with([
    'organized by the person' => [true],
    'organized by someone else' => [false],
]);

it('F-31: filters combine with AND', function () {
    [$group, $owner, $member] = dashboardGroup();
    $closed = ['closed_at' => now()->subDay()];
    $match = dashboardPoll($group, $member, 2, $member, 1, $closed);
    dashboardPoll($group, $owner, 2, $member, 0, $closed);
    dashboardPoll($group, $member, 2, $member, 2, $closed);
    dashboardPoll($group, $member, 2, $member, 0);

    expect(pollsIndexSlugs($member, ['mine' => 1, 'unanswered' => 1, 'all' => 1]))->toContain($match->slug)->toHaveCount(2)
        ->and(pollsIndexSlugs($member, ['mine' => 1, 'unanswered' => 1]))->toHaveCount(1)->not->toContain($match->slug);
});

it('F-31: an invalid filter value is rejected', function (array $query, string $key) {
    $this->actingAs(User::factory()->create())
        ->from(route('dashboard'))
        ->get(route('polls.index', $query))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors($key);
})->with([
    'word for a boolean' => [['mine' => 'banana'], 'mine'],
    'array for a boolean' => [['all' => [1]], 'all'],
    'word for unanswered' => [['unanswered' => 'x'], 'unanswered'],
]);

it('F-31: the page returns the applied filters', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('polls.index', ['unanswered' => 1, 'all' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['unanswered' => true, 'mine' => false, 'all' => true]));
});

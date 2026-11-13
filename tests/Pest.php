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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Give the user a grant on the event or poll, as joining through its link does.
 */
function grantOn(Event|Poll $entity, User $user): void
{
    $entity->accessGrants()->create(['user_id' => $user->id, 'joined_via' => 'link']);
}

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

/**
 * Create a list with two items, the first one claimed by the given user.
 */
function dashboardList(User $owner, array $attributes = [], ?User $claimer = null): Roster
{
    $roster = Roster::factory()->create(['owner_id' => $owner->id, ...$attributes]);
    $first = RosterItem::factory()->create(['roster_id' => $roster->id]);
    RosterItem::factory()->create(['roster_id' => $roster->id]);

    if ($claimer !== null) {
        RosterItemClaim::factory()->create(['roster_item_id' => $first->id, 'user_id' => $claimer->id]);
    }

    return $roster;
}

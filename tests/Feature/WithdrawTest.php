<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\PollResponse;
use App\Models\PollWithdrawal;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('F-08: a member withdraws from an open poll, loses their responses and the poll leaves their dashboard', function () {
    [$group, $owner, $member] = dashboardGroup();
    $other = User::factory()->create();
    $group->addMember($other, GroupRole::Member, now());
    $poll = dashboardPoll($group, $owner, 2, $member, 1);
    [$first, $second] = $poll->options->all();
    $second->responses()->create(['user_id' => $other->id, 'status' => PollResponseStatus::Yes]);

    $this->actingAs($member)
        ->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_withdraw', true));

    $this->actingAs($member)
        ->delete(route('polls.withdraw', $poll))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', __('ui.poll.withdrawn', ['poll' => $poll->title]));

    expect(PollResponse::query()->where('user_id', $member->id)->exists())->toBeFalse()
        ->and(PollWithdrawal::query()->where('poll_id', $poll->id)->where('user_id', $member->id)->exists())->toBeTrue();

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('polls', 0)
        ->where('groups.0.open_poll_count', 0));
    $this->actingAs($member)->get(route('polls.show', $poll))->assertNotFound();

    $this->actingAs($owner)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $page
        ->where('page.audience_count', 2)
        ->has('page.respondents', 2)
        ->where('page.best_option_slugs', [$second->slug]));
    expect($first->fresh()->load('poll')->countOf(PollResponseStatus::Yes))->toBe(0);
});

it('F-08: withdrawing from a poll is refused for its managers, an outsider and on a closed or fixed poll', function (Closure $setup) {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2, $member, 1);
    $user = $setup($poll, $owner, $member);

    $this->actingAs($user)->delete(route('polls.withdraw', $poll))->assertForbidden();

    expect(PollWithdrawal::query()->count())->toBe(0)
        ->and(PollResponse::query()->count())->toBe(1);
})->with([
    'organizer' => fn ($poll, $owner) => $owner,
    'group admin' => fn ($poll) => groupAdminOf($poll->group),
    'outsider' => fn () => User::factory()->create(),
    'closed poll' => function ($poll, $owner, $member) {
        $poll->close();

        return $member;
    },
    'fixed poll' => function ($poll, $owner, $member) {
        Event::factory()->create(['group_id' => $poll->group_id, 'organizer_id' => $owner->id, 'origin_poll_option_id' => $poll->options->first()->id]);

        return $member;
    },
]);

it('F-08: managers and everyone on a closed poll see no "Withdraw" action', function (Closure $setup) {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2);
    $user = $setup($poll, $owner, $member);

    $this->actingAs($user)
        ->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_withdraw', false));
})->with([
    'organizer' => fn ($poll, $owner) => $owner,
    'group admin' => fn ($poll) => groupAdminOf($poll->group),
    'closed poll' => function ($poll, $owner, $member) {
        $poll->close();

        return $member;
    },
]);

it('F-22: after withdrawing from a poll it is missing from "Waiting for you", the current polls and the group card count', function () {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 3, $member, 1);

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 1)
        ->has('polls', 1)
        ->where('groups.0.open_poll_count', 1));

    $this->actingAs($member)->delete(route('polls.withdraw', $poll))->assertRedirect(route('dashboard'));

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('polls', 0)
        ->where('groups.0.open_poll_count', 0));
});

it('F-27: a member who withdrew from a poll and joins through its link opens the poll again', function () {
    [$group, $owner, $member] = dashboardGroup();
    $poll = dashboardPoll($group, $owner, 2, $member, 1);
    $token = $poll->enableInviteLink();

    $this->actingAs($member)->delete(route('polls.withdraw', $poll))->assertRedirect(route('dashboard'));
    $this->actingAs($member)->get(route('polls.show', $poll))->assertNotFound();

    $this->actingAs($member)
        ->post(route('polls.join.store', $token))
        ->assertRedirect(route('polls.show', $poll));

    expect(PollWithdrawal::query()->count())->toBe(0);
    $this->actingAs($member)->get(route('polls.show', $poll))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_respond', true)
        ->where('page.permissions.can_withdraw', true));
});

it('F-11: a participant withdraws from an upcoming event, their claims are released and the event leaves their dashboard', function () {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);
    RosterItemClaim::factory()->create(['roster_item_id' => RosterItem::factory()->create(['roster_id' => $roster->id])->id, 'user_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_withdraw', true));

    $this->actingAs($member)
        ->delete(route('events.withdraw', $event))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', __('ui.event.withdrawn', ['event' => $event->title]));

    expect($event->participations()->where('user_id', $member->id)->exists())->toBeFalse()
        ->and(RosterItemClaim::query()->where('user_id', $member->id)->exists())->toBeFalse();

    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 0)
        ->has('upcoming', 0));
    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->where('page.own_status', null)
        ->where('page.permissions.can_withdraw', false));

    expect($event->participations()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('F-11: withdrawing from an event is refused for its managers, on a past event and without a participation', function (Closure $setup) {
    [$group, $owner, $member] = dashboardGroup();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Pending);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    $user = $setup($event, $owner, $member);
    $participations = $event->participations()->count();

    $this->actingAs($user)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_withdraw', false));
    $this->actingAs($user)->delete(route('events.withdraw', $event))->assertForbidden();

    expect($event->participations()->count())->toBe($participations);
})->with([
    'organizer' => fn ($event, $owner) => $owner,
    'group admin' => function ($event) {
        $admin = groupAdminOf($event->group);
        EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $admin->id, 'status' => ParticipationStatus::Pending]);

        return $admin;
    },
    'past event' => function ($event, $owner, $member) {
        $event->update(['date' => now()->subWeek()->format('Y-m-d')]);

        return $member;
    },
    'member without participation' => function ($event) {
        $user = User::factory()->create();
        $event->group->addMember($user, GroupRole::Member, now());

        return $user;
    },
]);

/**
 * Add a fresh admin to the group.
 */
function groupAdminOf(Group $group): User
{
    $admin = User::factory()->create();
    $group->addMember($admin, GroupRole::Admin, now());

    return $admin;
}

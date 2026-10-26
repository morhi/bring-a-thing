<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/**
 * Create a group whose owner organizes an event with one accepted, one pending and one uninvolved member.
 *
 * @return array{0: Group, 1: User, 2: Event, 3: User, 4: User, 5: User}
 */
function followUpGroup(): array
{
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    [$accepted, $pending, $uninvolved] = User::factory()->count(3)->create()->all();

    foreach ([$accepted, $pending, $uninvolved] as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $event->participations()->create(['user_id' => $owner->id, 'status' => ParticipationStatus::Accepted]);
    $event->participations()->create(['user_id' => $accepted->id, 'status' => ParticipationStatus::Accepted]);
    $event->participations()->create(['user_id' => $pending->id, 'status' => ParticipationStatus::Pending]);

    return [$group, $owner, $event, $accepted, $pending, $uninvolved];
}

/**
 * Valid input for a date finder follow-up poll with two days.
 *
 * @return array<string, mixed>
 */
function followUpPollInput(): array
{
    return [
        'title' => 'Rehearsal',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => '2027-05-03'], ['date' => '2027-05-04']],
    ];
}

/**
 * Create a follow-up poll on the event, organized by the given user, with one option.
 *
 * @return array{0: Poll, 1: PollOption}
 */
function followUpPoll(Event $event, User $organizer): array
{
    $poll = Poll::factory()->create(['group_id' => $event->group_id, 'organizer_id' => $organizer->id, 'event_id' => $event->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    return [$poll, $option];
}

it('F-12: an accepted participant creates a follow-up poll within the event', function () {
    [, , $event, $accepted] = followUpGroup();

    $this->actingAs($accepted)->get(route('events.polls.create', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Polls/Create', false)
            ->where('form.source_event.slug', $event->slug)
            ->where('form.member_count', 2));

    $this->actingAs($accepted)->post(route('events.polls.store', $event), followUpPollInput())
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Poll created.');

    $poll = Poll::query()->sole();

    expect($poll->event_id)->toBe($event->id)
        ->and($poll->group_id)->toBe($event->group_id)
        ->and($poll->organizer_id)->toBe($accepted->id)
        ->and($poll->options()->count())->toBe(2);
});

it('F-12: a group member without acceptance sees the follow-up poll neither in the group nor on the dashboard', function () {
    [$group, $owner, $event, $accepted, $pending, $uninvolved] = followUpGroup();
    [$poll] = followUpPoll($event, $owner);
    Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    foreach ([$pending, $uninvolved] as $member) {
        $this->actingAs($member)->get(route('groups.show', $group))
            ->assertInertia(fn (Assert $page) => $page->has('page.polls', 1)->where('page.polls.0.follow_up_event_title', null));
        $this->actingAs($member)->get(route('dashboard'))->assertOk()->assertDontSee($poll->slug);
    }

    $this->actingAs($accepted)->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->has('page.polls', 2));
});

it('F-12: a person with "pending" sees neither the follow-up polls nor the "New follow-up poll" action', function () {
    [, $owner, $event, , $pending] = followUpGroup();
    followUpPoll($event, $owner);

    $this->actingAs($pending)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->has('page.follow_up_polls', 0)
            ->where('page.permissions.can_create_follow_up_poll', false));

    $this->actingAs($pending)->get(route('events.polls.create', $event))->assertForbidden();
    $this->actingAs($pending)->post(route('events.polls.store', $event), followUpPollInput())->assertForbidden();

    expect(Poll::query()->count())->toBe(1);
});

it('F-12: a person who accepts after the follow-up poll was created can respond to it', function () {
    [, $owner, $event, , $pending] = followUpGroup();
    [$poll, $option] = followUpPoll($event, $owner);

    $this->actingAs($pending)->get(route('polls.show', $poll))->assertNotFound();

    $this->actingAs($pending)->put(route('events.participation.update', $event), ['status' => 'accepted'])->assertSessionHasNoErrors();

    $this->actingAs($pending)->get(route('polls.show', $poll))->assertOk();
    $this->actingAs($pending)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertSessionHasNoErrors();

    expect($option->responses()->where('user_id', $pending->id)->count())->toBe(1);
});

it('F-12: a person who declines no longer counts in totals, best option and fixing', function () {
    [, $owner, $event, $accepted] = followUpGroup();
    [$poll, $first] = followUpPoll($event, $owner);
    $second = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => $first->date->copy()->addDay()]);
    $first->responses()->create(['user_id' => $accepted->id, 'status' => PollResponseStatus::Yes]);
    $second->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);
    $second->responses()->create(['user_id' => $accepted->id, 'status' => PollResponseStatus::Yes]);

    $this->actingAs($accepted)->put(route('events.participation.update', $event), ['status' => 'declined'])->assertSessionHasNoErrors();

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.audience_count', 1)
            ->has('page.respondents', 1)
            ->where('page.poll.options.0.yes_count', 0)
            ->where('page.poll.options.1.yes_count', 1)
            ->where('page.best_option_slugs', [$second->slug]));

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$second->slug]])->assertSessionHas('success', 'Date fixed.');

    expect($second->event->participations()->pluck('user_id')->all())->toBe([$owner->id]);
});

it('F-12: an event fixed from a follow-up poll appears in the source event under the child events', function () {
    [, $owner, $event, $accepted] = followUpGroup();
    [$poll, $option] = followUpPoll($event, $owner);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success', 'Date fixed.');

    $child = $option->event()->sole();

    expect($child->parent_event_id)->toBe($event->id);

    $this->actingAs($accepted)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->has('page.child_events', 1)
            ->where('page.child_events.0.slug', $child->slug));
});

it('F-12: a child event shows no "New follow-up poll" action', function () {
    [$group, $owner, $event, $accepted] = followUpGroup();
    $child = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $event->id]);
    $child->participations()->create(['user_id' => $accepted->id, 'status' => ParticipationStatus::Accepted]);

    $this->actingAs($owner)->get(route('events.show', $child))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_create_follow_up_poll', false));
    $this->actingAs($accepted)->get(route('events.show', $child))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_create_follow_up_poll', false));

    $this->actingAs($owner)->get(route('events.polls.create', $child))->assertForbidden();
    $this->actingAs($accepted)->post(route('events.polls.store', $child), followUpPollInput())->assertForbidden();

    expect(Poll::query()->count())->toBe(0);
});

it('F-12: managers create follow-up polls on an event without acceptances, outsiders get "Not found"', function () {
    [$group, $owner, , , $pending] = followUpGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $pending->id]);
    $outsider = User::factory()->create();

    $this->actingAs($owner)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_create_follow_up_poll', true)
            ->where('page.follow_up_audience_count', 2));

    $this->actingAs($owner)->post(route('events.polls.store', $event), followUpPollInput())->assertSessionHas('success', 'Poll created.');
    $this->actingAs($outsider)->get(route('events.polls.create', $event))->assertNotFound();
    $this->actingAs($outsider)->post(route('events.polls.store', $event), followUpPollInput())->assertNotFound();

    expect(Poll::query()->where('event_id', $event->id)->count())->toBe(1);
});

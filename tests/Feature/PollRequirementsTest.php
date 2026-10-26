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

/**
 * Create a group with an owner and the given number of plain members, and a date finder poll by the owner.
 *
 * @return array{0: Group, 1: User, 2: array<int, User>, 3: Poll}
 */
function pollGroup(int $memberCount = 2, array $pollAttributes = []): array
{
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $members = User::factory()->count($memberCount)->create()->all();

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, ...$pollAttributes]);

    return [$group, $owner, $members, $poll];
}

/**
 * Record answers on an option, one per given user.
 *
 * @param  array<int, User>  $users
 */
function answerOption(PollOption $option, array $users, PollResponseStatus $status): void
{
    foreach ($users as $user) {
        $option->responses()->create(['user_id' => $user->id, 'status' => $status]);
    }
}

it('F-06: a poll with three time slots on the same date shows three separate columns', function () {
    [, $owner, , $poll] = pollGroup(pollAttributes: ['granularity' => 'hour']);
    PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12', 'starts_at' => '18:00', 'ends_at' => '19:00']);
    PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12', 'starts_at' => '10:00', 'ends_at' => '11:00']);
    PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12', 'starts_at' => '14:00', 'ends_at' => '15:00']);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Polls/Show', false)
            ->where('page.poll.uses_time_slots', true)
            ->where('page.poll.granularity', 'hour')
            ->has('page.poll.options', 3)
            ->where('page.poll.options.0.starts_at', '10:00')
            ->where('page.poll.options.1.starts_at', '14:00')
            ->where('page.poll.options.2.starts_at', '18:00'));
});

it('F-06: changing an answer from no to yes shows one yes answer and no additional row', function () {
    [, $owner, [$member], $poll] = pollGroup(1);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'no']);
    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes']);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->has('page.respondents', 2)
            ->where('page.poll.options.0.yes_count', 1)
            ->where('page.poll.options.0.no_count', 0));
});

it('F-06: two options with three yes and one no answer each both carry "Best option"', function () {
    [, $owner, $members, $poll] = pollGroup(4);
    $first = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01']);
    $second = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02']);
    answerOption($first, array_slice($members, 0, 3), PollResponseStatus::Yes);
    answerOption($first, [$members[3]], PollResponseStatus::No);
    answerOption($second, array_slice($members, 1, 3), PollResponseStatus::Yes);
    answerOption($second, [$members[0]], PollResponseStatus::No);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.best_option_slugs', [$first->slug, $second->slug])
            ->where('page.show_no_counts', false)
            ->where('page.poll.options.0.is_best', true)
            ->where('page.poll.options.1.is_best', true));
});

it('F-06: of two options with three yes answers, the one with fewer no answers alone carries "Best option"', function () {
    [, $owner, $members, $poll] = pollGroup(5);
    $first = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01']);
    $second = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02']);
    answerOption($first, array_slice($members, 0, 3), PollResponseStatus::Yes);
    answerOption($first, array_slice($members, 3, 2), PollResponseStatus::No);
    answerOption($second, array_slice($members, 0, 3), PollResponseStatus::Yes);
    answerOption($second, [$members[3]], PollResponseStatus::No);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.best_option_slugs', [$second->slug])
            ->where('page.show_no_counts', true)
            ->where('page.poll.options.0.is_best', false)
            ->where('page.poll.options.1.is_best', true));
});

it('F-06: "Use this time for all" with 18:00 to 20:00 on five slots sets all five to these times', function () {
    [$group, $owner] = pollGroup();
    $slots = collect(range(1, 5))->map(fn (int $day) => ['date' => "2027-03-0{$day}", 'starts_at' => '18:00', 'ends_at' => '20:00', 'label' => null])->all();

    $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Rehearsals',
        'type' => 'date_finder',
        'granularity' => 'hour',
        'options' => $slots,
    ])->assertSessionHasNoErrors();

    $poll = Poll::query()->where('title', 'Rehearsals')->firstOrFail();
    expect($poll->options)->toHaveCount(5)
        ->and($poll->options->every(fn (PollOption $option) => substr($option->starts_at, 0, 5) === '18:00' && substr($option->ends_at, 0, 5) === '20:00'))->toBeTrue();
});

it('F-06: an option with a label shows it in the grid header', function () {
    [, $owner, , $poll] = pollGroup();
    PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'Room free until 22:00']);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page->where('page.poll.options.0.label', 'Room free until 22:00'));
});

it('F-06: in an edited poll, "Use this time for all" leaves options with answers unchanged', function () {
    [, $owner, [$member], $poll] = pollGroup(1, ['granularity' => 'hour']);
    $answered = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01', 'starts_at' => '10:00', 'ends_at' => '12:00']);
    $open = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02', 'starts_at' => '10:00', 'ends_at' => '12:00']);
    answerOption($answered, [$member], PollResponseStatus::Yes);

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => $poll->title,
        'options' => [
            ['slug' => $answered->slug, 'date' => '2027-03-01', 'starts_at' => '10:00', 'ends_at' => '12:00'],
            ['slug' => $open->slug, 'date' => '2027-03-02', 'starts_at' => '18:00', 'ends_at' => '20:00'],
        ],
    ])->assertSessionHasNoErrors();

    expect(substr($answered->fresh()->starts_at, 0, 5))->toBe('10:00')
        ->and(substr($open->fresh()->starts_at, 0, 5))->toBe('18:00')
        ->and(substr($open->fresh()->ends_at, 0, 5))->toBe('20:00');
});

it('F-06: the mode cannot change after creation', function () {
    [, $owner, , $poll] = pollGroup();

    $this->actingAs($owner)->patch(route('polls.update', $poll), ['title' => 'Renamed', 'granularity' => 'hour', 'type' => 'attendance']);

    expect($poll->fresh()->granularity->value)->toBe('day')
        ->and($poll->fresh()->type->value)->toBe('date_finder');
});

it('F-07: the range 03.08. to 07.08. results in five options', function () {
    [$group, $owner] = pollGroup();

    $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Camp',
        'type' => 'attendance',
        'granularity' => 'day',
        'starts_on' => '2027-08-03',
        'ends_on' => '2027-08-07',
    ])->assertSessionHasNoErrors();

    expect(Poll::query()->where('title', 'Camp')->firstOrFail()->options)->toHaveCount(5);
});

it('F-07: an attendance poll offers no "Fix date" action and has no best option', function () {
    [, $owner, [$member], $poll] = pollGroup(1, ['type' => 'attendance']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    answerOption($option, [$member], PollResponseStatus::Yes);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_fix', false)
            ->where('page.permissions.can_close', true)
            ->where('page.best_option_slugs', []));

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertForbidden();
    expect($option->fresh()->isFixed())->toBeFalse();
});

it('F-08: a response to a closed poll is rejected with "This poll is closed."', function () {
    [, , [$member], $poll] = pollGroup(1);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $poll->close();

    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])
        ->assertSessionHasErrors(['status' => 'This poll is closed.']);

    expect($option->responses()->count())->toBe(0);
});

it('F-08: a member without the admin role sees no edit actions on other people\'s polls', function () {
    [, , [$member], $poll] = pollGroup(1);
    PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($member)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_edit', false)
            ->where('page.permissions.can_delete', false)
            ->where('page.permissions.can_fix', false)
            ->where('page.permissions.can_close', false)
            ->where('page.permissions.can_reopen', false)
            ->where('page.permissions.can_respond', true));

    $this->actingAs($member)->get(route('polls.edit', $poll))->assertForbidden();
});

it('F-08: after reopening a fixed poll, its events still exist', function () {
    [, $owner, , $poll] = pollGroup();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]]);

    $this->actingAs($owner)->delete(route('polls.reopen', $poll))->assertSessionHas('success', 'Poll reopened.');

    expect($poll->fresh()->isClosed())->toBeFalse()
        ->and(Event::query()->where('origin_poll_option_id', $option->id)->exists())->toBeTrue()
        ->and($option->fresh()->isFixed())->toBeTrue();
});

it('F-08: on an option with answers the date and time are locked, on an option without answers they can be edited', function () {
    [, $owner, [$member], $poll] = pollGroup(1);
    $answered = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01']);
    $open = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02']);
    answerOption($answered, [$member], PollResponseStatus::Maybe);

    $this->actingAs($owner)->get(route('polls.edit', $poll))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Polls/Create', false)
            ->where('form.poll.slug', $poll->slug)
            ->where('form.poll.options.0.is_schedule_locked', true)
            ->where('form.poll.options.1.is_schedule_locked', false));

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => $poll->title,
        'options' => [['slug' => $answered->slug, 'date' => '2027-03-05']],
    ])->assertSessionHasErrors(['options.0.date' => 'An option with answers keeps its date and time.']);

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => $poll->title,
        'options' => [
            ['slug' => $answered->slug, 'date' => '2027-03-01', 'label' => 'Lunch'],
            ['slug' => $open->slug, 'date' => '2027-03-06'],
            ['date' => '2027-03-07'],
        ],
    ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Poll updated.');

    expect($answered->fresh()->label)->toBe('Lunch')
        ->and($answered->fresh()->date->format('Y-m-d'))->toBe('2027-03-01')
        ->and($open->fresh()->date->format('Y-m-d'))->toBe('2027-03-06')
        ->and($poll->options()->count())->toBe(3);
});

it('F-08: a closed poll allows no changes to options', function () {
    [, $owner, , $poll] = pollGroup();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01']);
    $poll->close();

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => $poll->title,
        'options' => [['slug' => $option->slug, 'date' => '2027-03-02']],
    ])->assertSessionHasErrors(['options' => 'This poll is closed.']);

    expect($option->fresh()->date->format('Y-m-d'))->toBe('2027-03-01');
});

it('F-08: the "New poll" page is offered to group members', function () {
    [$group, , [$member]] = pollGroup(1);

    $this->actingAs($member)->get(route('polls.create', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Polls/Create', false)
            ->where('form.group_slug', $group->slug)
            ->where('form.member_count', 2)
            ->where('form.poll', null));
});

it('F-09: fixing three options creates three events with the options\' dates', function () {
    [, $owner, , $poll] = pollGroup();
    $options = collect(['2027-03-03', '2027-03-10', '2027-03-17', '2027-03-24'])
        ->map(fn (string $date) => PollOption::factory()->create(['poll_id' => $poll->id, 'date' => $date]));

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => $options->take(3)->pluck('slug')->all()])
        ->assertRedirect(route('polls.show', $poll))
        ->assertSessionHas('success', 'Dates fixed.');

    $events = Event::query()->where('group_id', $poll->group_id)->orderBy('date')->get();
    expect($events)->toHaveCount(3)
        ->and($events->map(fn (Event $event) => $event->date->format('Y-m-d'))->all())->toBe(['2027-03-03', '2027-03-10', '2027-03-17'])
        ->and($events->pluck('title')->unique()->all())->toBe([$poll->title])
        ->and($poll->fresh()->isClosed())->toBeTrue();

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->has('page.fixed_events', 3)
            ->where('page.poll.is_closed', true)
            ->where('page.poll.options.0.is_fixed', true)
            ->where('page.poll.options.3.is_fixed', false)
            ->where('page.permissions.can_reopen', true));
});

it('F-09: a person with yes on option A and maybe on option B is accepted for A and pending for B', function () {
    [, $owner, [$member], $poll] = pollGroup(1);
    $optionA = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01']);
    $optionB = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02']);
    answerOption($optionA, [$member], PollResponseStatus::Yes);
    answerOption($optionB, [$member], PollResponseStatus::Maybe);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$optionA->slug, $optionB->slug]]);

    expect($optionA->event->statusOf($member))->toBe(ParticipationStatus::Accepted)
        ->and($optionB->event->statusOf($member))->toBe(ParticipationStatus::Pending);
});

it('F-09: a person without a response appears as a participant in none of the events', function () {
    [, $owner, [$silent, $noSayer], $poll] = pollGroup(2);
    $optionA = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01']);
    $optionB = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02']);
    answerOption($optionA, [$noSayer], PollResponseStatus::No);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$optionA->slug, $optionB->slug]]);

    expect($optionA->event->statusOf($silent))->toBeNull()
        ->and($optionB->event->statusOf($silent))->toBeNull()
        ->and($optionA->event->statusOf($noSayer))->toBeNull()
        ->and($optionA->event->participations)->toHaveCount(0);
});

it('F-09: fixing without a selection is rejected', function () {
    [, $owner, , $poll] = pollGroup();
    PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => []])->assertSessionHasErrors('options');

    expect($poll->fresh()->isClosed())->toBeFalse();
});

it('F-09: a plain member cannot fix dates on someone else\'s poll', function () {
    [, , [$member], $poll] = pollGroup(1);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($member)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertForbidden();
});

it('F-09: a fixed option cannot be removed and keeps its date and time', function () {
    [, $owner, , $poll] = pollGroup(pollAttributes: ['granularity' => 'hour']);
    $fixed = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-01', 'starts_at' => '18:00', 'ends_at' => '20:00']);
    PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-02', 'starts_at' => '18:00', 'ends_at' => '20:00']);
    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$fixed->slug]]);
    $this->actingAs($owner)->delete(route('polls.reopen', $poll));

    $this->actingAs($owner)->delete(route('polls.options.destroy', [$poll, $fixed]))
        ->assertSessionHas('error', 'A fixed option cannot be removed.');

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => $poll->title,
        'options' => [['slug' => $fixed->slug, 'date' => '2027-03-01', 'starts_at' => '19:00', 'ends_at' => '21:00']],
    ])->assertSessionHasErrors(['options.0.date' => 'A fixed option keeps its date and time.']);

    $this->assertModelExists($fixed);
    expect(substr($fixed->fresh()->starts_at, 0, 5))->toBe('18:00');
});

it('F-09: an already fixed option cannot be fixed again', function () {
    [, $owner, , $poll] = pollGroup();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]]);
    $this->actingAs($owner)->delete(route('polls.reopen', $poll));

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])
        ->assertSessionHasErrors(['options' => 'An option is already fixed.']);

    expect(Event::query()->where('origin_poll_option_id', $option->id)->count())->toBe(1);
});

it('F-09: a reopened poll with a fixed option closes without a new selection', function () {
    [, $owner, , $poll] = pollGroup();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]]);
    $this->actingAs($owner)->delete(route('polls.reopen', $poll));

    $this->actingAs($owner)->post(route('polls.close', $poll))->assertSessionHas('success', 'Poll closed.');

    expect($poll->fresh()->isClosed())->toBeTrue();
});

it('F-09: events fixed from a follow-up poll get the source event as parent', function () {
    [$group, $owner, , $poll] = pollGroup();
    $source = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $poll->update(['title' => 'Rehearsal']);
    $poll->event_id = $source->id;
    $poll->save();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])
        ->assertSessionHas('success', 'Date fixed.');

    expect($option->event->parent_event_id)->toBe($source->id);
});

it('F-12: a group member without acceptance can neither see nor answer a follow-up poll', function (?ParticipationStatus $status) {
    [$group, $owner, [$member], $poll] = pollGroup(1);
    $source = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $poll->event_id = $source->id;
    $poll->save();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    if ($status !== null) {
        $source->participations()->create(['user_id' => $member->id, 'status' => $status]);
    }

    $this->actingAs($member)->get(route('polls.show', $poll))->assertNotFound();
    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertNotFound();

    expect($option->responses()->count())->toBe(0);
})->with([
    'without participation' => [null],
    'pending' => [ParticipationStatus::Pending],
    'declined' => [ParticipationStatus::Declined],
]);

it('F-12: an accepted participant sees and answers a follow-up poll', function () {
    [$group, $owner, [$member], $poll] = pollGroup(1);
    $source = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $source->participations()->create(['user_id' => $member->id, 'status' => ParticipationStatus::Accepted]);
    $poll->event_id = $source->id;
    $poll->save();
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($member)->get(route('polls.show', $poll))->assertOk();
    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertSessionHasNoErrors();

    expect($option->responses()->count())->toBe(1);
});

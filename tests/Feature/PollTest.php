<?php

use App\Enums\GroupRole;
use App\Enums\PollResponseStatus;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;

function createGroupWithMembers(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $member];
}

it('creates a date-finder poll from explicit day options', function () {
    [$group, $owner] = createGroupWithMembers();

    $response = $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Dinner night',
        'type' => 'date_finder',
        'granularity' => 'day',
        // starts_at/ends_at/label sent as explicit nulls, matching what the
        // real create-poll form always submits regardless of granularity.
        'options' => [
            ['date' => '2026-10-01', 'starts_at' => null, 'ends_at' => null, 'label' => null],
            ['date' => '2026-10-02', 'starts_at' => null, 'ends_at' => null, 'label' => null],
        ],
    ]);

    $poll = Poll::query()->where('title', 'Dinner night')->firstOrFail();
    $response->assertRedirect(route('polls.show', $poll));
    $response->assertSessionHas('success', 'Poll created.');
    expect($poll->organizer_id)->toBe($owner->id);
    expect($poll->group_id)->toBe($group->id);
    expect($poll->options()->count())->toBe(2);
    expect($poll->options()->first()->starts_at)->toBeNull();
});

it('creates a date-finder poll from explicit hour slots', function () {
    [$group, $owner] = createGroupWithMembers();

    $response = $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Call slots',
        'type' => 'date_finder',
        'granularity' => 'hour',
        'options' => [
            ['date' => '2026-10-01', 'starts_at' => '09:00', 'ends_at' => '10:00', 'label' => 'Morning'],
        ],
    ]);

    $response->assertRedirect();
    $poll = Poll::query()->where('title', 'Call slots')->firstOrFail();
    $option = $poll->options()->firstOrFail();
    expect($option->starts_at)->toBe('09:00');
    expect($option->ends_at)->toBe('10:00');
    expect($option->label)->toBe('Morning');
});

it('rejects an hour-granularity option whose end time is not after its start time', function () {
    [$group, $owner] = createGroupWithMembers();

    $response = $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Call slots',
        'type' => 'date_finder',
        'granularity' => 'hour',
        'options' => [
            ['date' => '2026-10-01', 'starts_at' => '10:00', 'ends_at' => '09:00'],
        ],
    ]);

    $response->assertSessionHasErrors('options.0.ends_at');
});

it('expands an attendance poll date range into one option per day', function () {
    [$group, $owner] = createGroupWithMembers();

    $response = $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Cabin week',
        'type' => 'attendance',
        'granularity' => 'day',
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-03',
    ]);

    $response->assertRedirect();
    $poll = Poll::query()->where('title', 'Cabin week')->firstOrFail();
    expect($poll->options()->count())->toBe(3);
    expect($poll->options()->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-10-01', '2026-10-02', '2026-10-03']);
});

it('creates an attendance poll with custom per-day hour slots', function () {
    [$group, $owner] = createGroupWithMembers();

    $response = $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Cabin week meals',
        'type' => 'attendance',
        'granularity' => 'hour',
        'options' => [
            ['date' => '2026-10-01', 'starts_at' => '12:00', 'ends_at' => '13:00', 'label' => 'Lunch'],
            ['date' => '2026-10-01', 'starts_at' => '18:00', 'ends_at' => '19:00', 'label' => 'Dinner'],
        ],
    ]);

    $response->assertRedirect();
    $poll = Poll::query()->where('title', 'Cabin week meals')->firstOrFail();
    expect($poll->options()->count())->toBe(2);
    expect($poll->options()->pluck('label')->all())->toBe(['Lunch', 'Dinner']);
});

it('forbids a non-member from creating a poll on a group', function () {
    [$group] = createGroupWithMembers();
    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->post(route('polls.store', $group), [
        'title' => 'Dinner night',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => '2026-10-01']],
    ]);

    $response->assertForbidden();
});

it('allows a group member to view the poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $response = $this->actingAs($member)->get(route('polls.show', $poll));

    $response->assertOk();
});

it('forbids a non-member from viewing the poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $outsider = User::factory()->create();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $response = $this->actingAs($outsider)->get(route('polls.show', $poll));

    $response->assertForbidden();
});

it('lets a member record and update their response to an option', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])
        ->assertSessionHas('success', 'Response saved.');
    expect($option->responses()->where('user_id', $member->id)->value('status'))->toBe(PollResponseStatus::Yes);

    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'maybe']);

    expect($option->responses()->where('user_id', $member->id)->count())->toBe(1);
    expect($option->responses()->where('user_id', $member->id)->value('status'))->toBe(PollResponseStatus::Maybe);
});

it('forbids a non-member from responding to an option', function () {
    [$group, $owner] = createGroupWithMembers();
    $outsider = User::factory()->create();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $response = $this->actingAs($outsider)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes']);

    $response->assertForbidden();
});

it('rejects a response to an option that does not belong to the given poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $otherPoll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $otherPoll->id]);

    $response = $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes']);

    $response->assertNotFound();
});

it('allows the organizer to rename the poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Old title']);

    $response = $this->actingAs($owner)->patch(route('polls.update', $poll), ['title' => 'New title']);

    $response->assertRedirect(route('polls.edit', $poll));
    $response->assertSessionHas('success', 'Poll updated.');
    expect($poll->fresh()->title)->toBe('New title');
});

it('allows a group admin to rename a poll they did not organize', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $group->members()->updateExistingPivot($member, ['role' => GroupRole::Admin]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Old title']);

    $response = $this->actingAs($member)->patch(route('polls.update', $poll), ['title' => 'New title']);

    $response->assertRedirect(route('polls.edit', $poll));
    expect($poll->fresh()->title)->toBe('New title');
});

it('forbids a plain member from renaming the poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $response = $this->actingAs($member)->patch(route('polls.update', $poll), ['title' => 'New title']);

    $response->assertForbidden();
});

it('allows the organizer to delete the poll, its options, and its responses', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $option->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);

    $response = $this->actingAs($owner)->delete(route('polls.destroy', $poll));

    $response->assertRedirect(route('groups.show', $group));
    $response->assertSessionHas('success', 'Poll deleted.');
    $this->assertModelMissing($poll);
    $this->assertModelMissing($option);
});

it('forbids a plain member from deleting the poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $response = $this->actingAs($member)->delete(route('polls.destroy', $poll));

    $response->assertForbidden();
    $this->assertModelExists($poll);
});

it('lets the organizer add a day option to the poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'granularity' => 'day']);

    $response = $this->actingAs($owner)->post(route('polls.options.store', $poll), ['date' => '2026-11-01']);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Option added.');
    expect($poll->options()->count())->toBe(1);
});

it('lets the organizer add a day option when the form sends explicit null time fields', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'granularity' => 'day']);

    $response = $this->actingAs($owner)->post(route('polls.options.store', $poll), [
        'date' => '2026-11-01',
        'starts_at' => null,
        'ends_at' => null,
        'label' => null,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect($poll->options()->count())->toBe(1);
});

it('requires start and end times when adding an option to an hour-granularity poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'granularity' => 'hour']);

    $response = $this->actingAs($owner)->post(route('polls.options.store', $poll), ['date' => '2026-11-01']);

    $response->assertSessionHasErrors(['starts_at', 'ends_at']);
    expect($poll->options()->count())->toBe(0);
});

it('forbids a plain member from adding an option to the poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $response = $this->actingAs($member)->post(route('polls.options.store', $poll), ['date' => '2026-11-01']);

    $response->assertForbidden();
});

it('lets the organizer remove an option and its responses', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $option->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);

    $response = $this->actingAs($owner)->delete(route('polls.options.destroy', [$poll, $option]));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Option removed.');
    $this->assertModelMissing($option);
});

it('forbids a plain member from removing an option', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $response = $this->actingAs($member)->delete(route('polls.options.destroy', [$poll, $option]));

    $response->assertForbidden();
    $this->assertModelExists($option);
});

it('rejects removing an option that does not belong to the given poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $otherPoll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $otherPoll->id]);

    $response = $this->actingAs($owner)->delete(route('polls.options.destroy', [$poll, $option]));

    $response->assertNotFound();
    $this->assertModelExists($option);
});

it('reports the option(s) with the most yes votes as best for a date-finder poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $winning = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2026-10-01']);
    $losing = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2026-10-02']);
    $winning->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);
    $winning->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);
    $losing->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);

    expect($poll->bestOptions()->pluck('id')->all())->toBe([$winning->id]);
});

it('closes a date-finder poll with a chosen option', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'date_finder']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $response = $this->actingAs($owner)->post(route('polls.close', $poll), ['option_id' => $option->id]);

    $response->assertRedirect(route('polls.edit', $poll));
    $response->assertSessionHas('success', 'Poll closed.');
    $poll->refresh();
    expect($poll->isClosed())->toBeTrue();
    expect($poll->chosen_option_id)->toBe($option->id);
});

it('requires a chosen option when closing a date-finder poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'date_finder']);

    $response = $this->actingAs($owner)->post(route('polls.close', $poll), []);

    $response->assertSessionHasErrors('option_id');
    expect($poll->fresh()->isClosed())->toBeFalse();
});

it('rejects a chosen option belonging to a different poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'date_finder']);
    $otherPoll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $otherPoll->id]);

    $response = $this->actingAs($owner)->post(route('polls.close', $poll), ['option_id' => $option->id]);

    $response->assertSessionHasErrors('option_id');
});

it('closes an attendance poll without a chosen option', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'attendance']);

    $response = $this->actingAs($owner)->post(route('polls.close', $poll), []);

    $response->assertRedirect(route('polls.edit', $poll));
    $poll->refresh();
    expect($poll->isClosed())->toBeTrue();
    expect($poll->chosen_option_id)->toBeNull();
});

it('rejects an option_id when closing an attendance poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'attendance']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $response = $this->actingAs($owner)->post(route('polls.close', $poll), ['option_id' => $option->id]);

    $response->assertSessionHasErrors('option_id');
});

it('forbids a plain member from closing the poll', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'attendance']);

    $response = $this->actingAs($member)->post(route('polls.close', $poll), []);

    $response->assertForbidden();
    expect($poll->fresh()->isClosed())->toBeFalse();
});

it('allows a group admin to close and reopen a poll they did not organize', function () {
    [$group, $owner, $member] = createGroupWithMembers();
    $group->members()->updateExistingPivot($member, ['role' => GroupRole::Admin]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'attendance']);

    $this->actingAs($member)->post(route('polls.close', $poll), [])->assertRedirect();
    expect($poll->fresh()->isClosed())->toBeTrue();

    $this->actingAs($member)->delete(route('polls.reopen', $poll))->assertRedirect(route('polls.edit', $poll));
    expect($poll->fresh()->isClosed())->toBeFalse();
});

it('reopens a poll and clears the chosen option', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'date_finder']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $poll->close($option);

    $response = $this->actingAs($owner)->delete(route('polls.reopen', $poll));

    $response->assertSessionHas('success', 'Poll reopened.');
    $poll->refresh();
    expect($poll->isClosed())->toBeFalse();
    expect($poll->chosen_option_id)->toBeNull();
});

it('rejects a response to a closed poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $poll->close($option);

    $response = $this->actingAs($owner)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes']);

    $response->assertSessionHasErrors('status');
    expect($option->responses()->count())->toBe(0);
});

it('rejects adding an option to a closed poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'attendance']);
    $poll->close();

    $response = $this->actingAs($owner)->post(route('polls.options.store', $poll), ['date' => '2026-11-01']);

    $response->assertSessionHasErrors('date');
    expect($poll->options()->count())->toBe(0);
});

it('rejects removing an option from a closed poll', function () {
    [$group, $owner] = createGroupWithMembers();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'type' => 'attendance']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $poll->close();

    $response = $this->actingAs($owner)->delete(route('polls.options.destroy', [$poll, $option]));

    $response->assertSessionHas('error', 'This poll is closed.');
    $this->assertModelExists($option);
});

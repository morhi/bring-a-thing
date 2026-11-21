<?php

use App\Enums\GroupRole;
use App\Events\DashboardChanged;
use App\Events\PollChanged;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A group with an owner in Berlin and a member in New York.
 *
 * @return array{0: Group, 1: User, 2: User}
 */
function deadlineGroup(): array
{
    $owner = User::factory()->create(['timezone' => 'Europe/Berlin']);
    $member = User::factory()->create(['timezone' => 'America/New_York']);
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $member];
}

it('F-08: stores the deadline entered in the editor as UTC from the editor\'s zone', function () {
    [$group, $owner] = deadlineGroup();

    $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Rehearsals',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => '2026-12-28']],
        'deadline_date' => '2026-12-20',
        'deadline_time' => '18:00',
        'auto_close' => true,
    ])->assertRedirect();

    $poll = Poll::query()->where('title', 'Rehearsals')->firstOrFail();
    expect($poll->answer_deadline->utc()->format('Y-m-d H:i'))->toBe('2026-12-20 17:00')
        ->and($poll->auto_close)->toBeTrue();
});

it('F-08: validates the deadline fields', function (array $input, string $errorKey) {
    [$group, $owner] = deadlineGroup();

    $this->actingAs($owner)->post(route('polls.store', $group), [
        'title' => 'Rehearsals',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => '2026-12-28']],
        ...$input,
    ])->assertSessionHasErrors($errorKey);

    expect(Poll::query()->count())->toBe(0);
})->with([
    'auto-close without deadline' => [['auto_close' => true], 'auto_close'],
    'time without date' => [['deadline_time' => '18:00'], 'deadline_date'],
    'date without time' => [['deadline_date' => '2026-12-20'], 'deadline_time'],
    'malformed time' => [['deadline_date' => '2026-12-20', 'deadline_time' => '25:99'], 'deadline_time'],
]);

it('F-08: edits and clears the deadline, while a rename without the fields keeps it', function () {
    [$group, $owner] = deadlineGroup();
    $poll = Poll::factory()->for($group)->create(['organizer_id' => $owner->id]);

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => 'Renamed',
        'deadline_date' => '2026-12-20',
        'deadline_time' => '18:00',
        'auto_close' => true,
    ])->assertSessionHasNoErrors();

    expect($poll->fresh()->answer_deadline->utc()->format('Y-m-d H:i'))->toBe('2026-12-20 17:00');

    $this->actingAs($owner)->patch(route('polls.update', $poll), ['title' => 'Renamed again'])->assertSessionHasNoErrors();

    expect($poll->fresh()->answer_deadline)->not->toBeNull();

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => 'Renamed again',
        'deadline_date' => null,
        'deadline_time' => null,
        'auto_close' => true,
    ])->assertSessionHasErrors('auto_close');

    $this->actingAs($owner)->patch(route('polls.update', $poll), [
        'title' => 'Renamed again',
        'deadline_date' => null,
        'deadline_time' => null,
        'auto_close' => false,
    ])->assertSessionHasNoErrors();

    expect($poll->fresh()->answer_deadline)->toBeNull()
        ->and($poll->fresh()->auto_close)->toBeFalse();
});

it('F-08: sends the deadline as one UTC instant that each viewer\'s zone turns into their local time', function () {
    Carbon::setTestNow('2026-12-01 10:00:00');
    [$group, $owner, $member] = deadlineGroup();
    $poll = Poll::factory()->for($group)->withDeadline(Carbon::parse('2026-12-20 18:00', 'Europe/Berlin'))->create(['organizer_id' => $owner->id]);

    foreach ([[$owner, '18:00'], [$member, '12:00']] as [$viewer, $localTime]) {
        $this->actingAs($viewer)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $page
            ->where('page.poll.answer_deadline', '2026-12-20T17:00:00Z')
            ->where('page.poll.has_passed_deadline', false)
            ->where('auth.user.timezone', $viewer->timezone));

        expect(Carbon::parse('2026-12-20T17:00:00Z')->setTimezone($viewer->timezone)->format('H:i'))->toBe($localTime);
    }
});

it('F-08: shows the deadline on the dashboard poll rows and on All polls', function () {
    [$group, $owner] = deadlineGroup();
    Poll::factory()->for($group)->withDeadline(Carbon::parse('2026-12-20 17:00', 'UTC'))->create(['organizer_id' => $owner->id]);

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('polls.0.answer_deadline', '2026-12-20T17:00:00Z'));

    $this->actingAs($owner)->get(route('polls.index'))->assertInertia(fn (Assert $page) => $page
        ->where('polls.0.answer_deadline', '2026-12-20T17:00:00Z'));
});

it('F-08: keeps accepting answers after the deadline without auto-close and flags the deadline as passed', function () {
    [$group, $owner, $member] = deadlineGroup();
    $poll = Poll::factory()->for($group)->withDeadline(now()->subHour())->create(['organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->artisan('polls:close-at-deadline')->assertSuccessful();

    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertSessionHasNoErrors();

    expect($option->responses()->count())->toBe(1)
        ->and($poll->fresh()->isClosed())->toBeFalse();

    $this->actingAs($member)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $page
        ->where('page.poll.has_passed_deadline', true)
        ->where('page.permissions.can_respond', true));
});

it('F-08: the auto-close command closes only open polls with auto-close whose deadline passed, then rejects responses', function () {
    Event::fake([PollChanged::class, DashboardChanged::class]);
    [$group, $owner, $member] = deadlineGroup();
    $due = Poll::factory()->for($group)->withDeadline(now()->subMinute(), autoClose: true)->create(['organizer_id' => $owner->id]);
    $future = Poll::factory()->for($group)->withDeadline(now()->addHour(), autoClose: true)->create(['organizer_id' => $owner->id]);
    $withoutAutoClose = Poll::factory()->for($group)->withDeadline(now()->subMinute())->create(['organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $due->id]);

    $this->artisan('polls:close-at-deadline')->assertSuccessful();

    expect($due->fresh()->isClosed())->toBeTrue()
        ->and($future->fresh()->isClosed())->toBeFalse()
        ->and($withoutAutoClose->fresh()->isClosed())->toBeFalse();
    Event::assertDispatched(PollChanged::class, fn (PollChanged $event) => $event->pollId === $due->id);

    $this->actingAs($member)->post(route('polls.responses.store', [$due, $option]), ['status' => 'yes'])->assertSessionHasErrors('status');
    expect($option->responses()->count())->toBe(0);
});

it('F-08: a date finder still allows fixing dates after auto-close', function () {
    [$group, $owner] = deadlineGroup();
    $poll = Poll::factory()->for($group)->withDeadline(now()->subMinute(), autoClose: true)->create(['organizer_id' => $owner->id, 'type' => 'date_finder']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->artisan('polls:close-at-deadline')->assertSuccessful();
    expect($poll->fresh()->isClosed())->toBeTrue();

    $this->actingAs($owner)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_fix', true));

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHasNoErrors();

    expect($option->fresh()->isFixed())->toBeTrue();
});

it('F-08: a poll closed by hand still refuses fixing', function () {
    [$group, $owner] = deadlineGroup();
    $poll = Poll::factory()->for($group)->create(['organizer_id' => $owner->id, 'type' => 'date_finder']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $poll->close();

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHasErrors('options');
});

it('F-08: reopening a poll with a passed deadline and auto-close leaves it open and turns auto-close off', function () {
    [$group, $owner] = deadlineGroup();
    $poll = Poll::factory()->for($group)->withDeadline(now()->subMinute(), autoClose: true)->create(['organizer_id' => $owner->id, 'type' => 'attendance']);
    $this->artisan('polls:close-at-deadline')->assertSuccessful();

    $this->actingAs($owner)->delete(route('polls.reopen', $poll))->assertSessionHas('success');
    $this->artisan('polls:close-at-deadline')->assertSuccessful();

    $poll->refresh();
    expect($poll->isClosed())->toBeFalse()
        ->and($poll->auto_close)->toBeFalse()
        ->and($poll->answer_deadline)->not->toBeNull();
});

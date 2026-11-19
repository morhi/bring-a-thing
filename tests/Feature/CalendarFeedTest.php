<?php

use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;

/**
 * Enable the feed for a user and return the user with the feed url's token.
 *
 * @return array{0: User, 1: string}
 */
function calendarUser(array $attributes = []): array
{
    $user = User::factory()->create($attributes);

    return [$user, $user->enableCalendarFeed()];
}

/**
 * Fetch the user's feed body.
 */
function feedOf(User $user): string
{
    return test()->get(route('calendar.feed', $user->fresh()->calendar_token))->assertOk()->getContent();
}

/**
 * The VEVENT block carrying the UID prefix, or null when absent.
 */
function feedBlock(string $body, string $uidPrefix): ?string
{
    foreach (explode('BEGIN:VEVENT', $body) as $block) {
        if (str_contains($block, 'UID:'.$uidPrefix)) {
            return $block;
        }
    }

    return null;
}

/**
 * An open date finder poll in a group with the user as member, and one option answered by them.
 *
 * @return array{0: User, 1: User, 2: Poll, 3: PollOption}
 */
function reservedSetup(PollResponseStatus $status = PollResponseStatus::Yes, array $pollAttributes = [], array $optionAttributes = []): array
{
    [, $owner, $members, $poll] = pollGroup(1, $pollAttributes);
    $member = $members[0];
    $member->enableCalendarFeed();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, ...$optionAttributes]);
    answerOption($option, [$member], $status);

    return [$member, $owner, $poll, $option];
}

it('F-34: an unknown link returns 404 and the current link returns 200', function () {
    [$user, $token] = calendarUser();

    $this->get(route('calendar.feed', 'unknown'))->assertNotFound();
    $this->get(route('calendar.feed', $token))->assertOk();
});

it('F-34: the feed is served without login as text/calendar', function () {
    [$user, $token] = calendarUser();
    [, , $member] = dashboardGroup();
    $event = dashboardEvent(null, $user, $user, ParticipationStatus::Accepted);

    foreach ([null, $member] as $viewer) {
        $viewer === null ?: $this->actingAs($viewer);

        $response = $this->get(route('calendar.feed', $token))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'inline; filename="bring-a-thing.ics"');

        expect($response->getContent())->toStartWith('BEGIN:VCALENDAR')
            ->and(trim($response->getContent()))->toEndWith('END:VCALENDAR')
            ->and($response->getContent())->toContain('UID:event-'.$event->slug.'@');
    }
});

it('F-34: an accepted event is confirmed and a pending event is tentative', function () {
    [$group, $owner, $member] = dashboardGroup();
    $member->enableCalendarFeed();
    $accepted = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['title' => 'Barbecue']);
    $pending = dashboardEvent($group, $owner, $member, ParticipationStatus::Pending, ['title' => 'Picnic']);

    $body = feedOf($member);

    expect(feedBlock($body, 'event-'.$accepted->slug.'@'))->toContain('STATUS:CONFIRMED')->toContain('SUMMARY:Barbecue')
        ->and(feedBlock($body, 'event-'.$pending->slug.'@'))->toContain('STATUS:TENTATIVE')->toContain('SUMMARY:Picnic');
});

it('F-34: events without an accepted or pending participation are absent', function (string $case) {
    [$group, $owner, $member] = dashboardGroup();
    $member->enableCalendarFeed();
    $event = dashboardEvent($group, $owner, $case === 'declined' ? $member : null, $case === 'declined' ? ParticipationStatus::Declined : null);

    if ($case === 'other member') {
        EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $owner->id, 'status' => ParticipationStatus::Accepted]);
    }

    expect(feedOf($member))->not->toContain('UID:event-'.$event->slug.'@');
})->with(['declined', 'no participation', 'other member']);

it('F-34: a child event with an accepted participation appears like any other event', function () {
    [$group, $owner, $member] = dashboardGroup();
    $member->enableCalendarFeed();
    $parent = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);
    $child = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted, ['parent_event_id' => $parent->id]);

    expect(feedBlock(feedOf($member), 'event-'.$child->slug.'@'))->toContain('STATUS:CONFIRMED');
});

it('F-34: an event the person can no longer access is absent', function () {
    [$group, $owner, $member] = dashboardGroup();
    $member->enableCalendarFeed();
    $event = dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted);

    expect(feedOf($member))->toContain('UID:event-'.$event->slug.'@');

    $group->members()->detach($member);

    expect(feedOf($member))->not->toContain('UID:event-'.$event->slug.'@');
});

it('F-34: events are visible through each access path of the scope', function (string $path) {
    [$group, $owner, $member] = dashboardGroup();
    $stranger = User::factory()->create();
    $member->enableCalendarFeed();

    $event = match ($path) {
        'group member' => dashboardEvent($group, $owner, $member, ParticipationStatus::Accepted),
        'standalone organizer' => dashboardEvent(null, $member, $member, ParticipationStatus::Accepted),
        'grant' => tap(dashboardEvent(null, $stranger, $member, ParticipationStatus::Accepted), fn (Event $event) => grantOn($event, $member)),
    };
    $hidden = dashboardEvent(null, $stranger, $member, ParticipationStatus::Accepted);

    $body = feedOf($member);

    expect($body)->toContain('UID:event-'.$event->slug.'@')
        ->and($body)->not->toContain('UID:event-'.$hidden->slug.'@');
})->with(['group member', 'standalone organizer', 'grant']);

it('F-34: a timed event uses floating start and end times', function () {
    [$user] = calendarUser();
    $event = dashboardEvent(null, $user, $user, ParticipationStatus::Accepted, ['date' => '2027-03-12', 'starts_at' => '18:00', 'ends_at' => '20:30']);

    $block = feedBlock(feedOf($user), 'event-'.$event->slug.'@');

    expect($block)->toContain("DTSTART:20270312T180000\r\n")->toContain("DTEND:20270312T203000\r\n")->not->toContain('TZID');
});

it('F-34: an event without a start time is all-day with an exclusive end date', function (?string $endDate, string $expectedEnd) {
    [$user] = calendarUser();
    $event = dashboardEvent(null, $user, $user, ParticipationStatus::Accepted, ['date' => '2027-03-12', 'starts_at' => null, 'ends_at' => null, 'end_date' => $endDate]);

    $block = feedBlock(feedOf($user), 'event-'.$event->slug.'@');

    expect($block)->toContain('DTSTART;VALUE=DATE:20270312')->toContain('DTEND;VALUE=DATE:'.$expectedEnd);
})->with([
    'single day' => [null, '20270313'],
    'several days' => ['2027-03-14', '20270315'],
]);

it('F-34: an event with an end date and times spans the days', function (?string $endsAt, string $expectedEnd) {
    [$user] = calendarUser();
    $event = dashboardEvent(null, $user, $user, ParticipationStatus::Accepted, ['date' => '2027-03-12', 'starts_at' => '22:00', 'end_date' => '2027-03-13', 'ends_at' => $endsAt]);

    expect(feedBlock(feedOf($user), 'event-'.$event->slug.'@'))
        ->toContain("DTSTART:20270312T220000\r\n")->toContain("DTEND:{$expectedEnd}\r\n");
})->with([
    'with end time' => ['02:00', '20270313T020000'],
    'without end time' => [null, '20270314T000000'],
]);

it('F-34: a yes and a maybe option of an open poll are reserved, a no option is not', function () {
    [$member, , $poll, $yes] = reservedSetup(pollAttributes: ['title' => 'Summer trip']);
    $maybe = PollOption::factory()->create(['poll_id' => $poll->id]);
    $no = PollOption::factory()->create(['poll_id' => $poll->id]);
    $unanswered = PollOption::factory()->create(['poll_id' => $poll->id]);
    answerOption($maybe, [$member], PollResponseStatus::Maybe);
    answerOption($no, [$member], PollResponseStatus::No);

    $body = feedOf($member);

    expect(feedBlock($body, 'poll-option-'.$yes->slug.'@'))->toContain('STATUS:TENTATIVE')->toContain('SUMMARY:Reserved: Summer trip')
        ->and(feedBlock($body, 'poll-option-'.$maybe->slug.'@'))->toContain('STATUS:TENTATIVE')
        ->and($body)->not->toContain('poll-option-'.$no->slug)
        ->and($body)->not->toContain('poll-option-'.$unanswered->slug);
});

it('F-34: an attendance poll reserves its answered options too', function () {
    [$member, , , $option] = reservedSetup(pollAttributes: ['type' => 'attendance']);

    expect(feedBlock(feedOf($member), 'poll-option-'.$option->slug.'@'))->toContain('STATUS:TENTATIVE');
});

it('F-34: closing the poll removes the reserved entry', function () {
    [$member, $owner, $poll, $option] = reservedSetup(pollAttributes: ['type' => 'attendance']);

    expect(feedOf($member))->toContain('poll-option-'.$option->slug);

    $this->actingAs($owner)->post(route('polls.close', $poll))->assertSessionHasNoErrors();

    $body = feedOf($member);

    expect($body)->not->toContain('poll-option-'.$option->slug)->not->toContain('BEGIN:VEVENT');
});

it('F-34: fixing the option replaces the reserved entry with the event', function (PollResponseStatus $answer, string $status) {
    [$member, $owner, $poll, $option] = reservedSetup($answer);
    $other = PollOption::factory()->create(['poll_id' => $poll->id]);
    answerOption($other, [$member], PollResponseStatus::Yes);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHasNoErrors();

    $body = feedOf($member);
    $event = Event::where('origin_poll_option_id', $option->id)->firstOrFail();

    expect($body)->not->toContain('poll-option-')
        ->and(feedBlock($body, 'event-'.$event->slug.'@'))->toContain('STATUS:'.$status);
})->with([
    'yes' => [PollResponseStatus::Yes, 'CONFIRMED'],
    'maybe' => [PollResponseStatus::Maybe, 'TENTATIVE'],
]);

it('F-34: a reopened poll does not bring back the reserved entry of a fixed option', function () {
    [$member, $owner, $poll, $fixed] = reservedSetup();
    $second = PollOption::factory()->create(['poll_id' => $poll->id]);
    answerOption($second, [$member], PollResponseStatus::Yes);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$fixed->slug]])->assertSessionHasNoErrors();
    $this->actingAs($owner)->delete(route('polls.reopen', $poll))->assertSessionHasNoErrors();

    $body = feedOf($member);
    $event = Event::where('origin_poll_option_id', $fixed->id)->firstOrFail();

    expect($poll->fresh()->isClosed())->toBeFalse()
        ->and($body)->not->toContain('poll-option-'.$fixed->slug)
        ->and($body)->toContain('poll-option-'.$second->slug)
        ->and($body)->toContain('UID:event-'.$event->slug.'@');
});

it('F-34: withdrawing from a poll removes the reserved entry', function () {
    [$member, , $poll, $option] = reservedSetup();

    expect(feedOf($member))->toContain('poll-option-'.$option->slug);

    $this->actingAs($member)->delete(route('polls.withdraw', $poll))->assertSessionHasNoErrors();

    expect(feedOf($member))->not->toContain('poll-option-'.$option->slug);
});

it('F-34: a poll the person can no longer access gives no reserved entry', function () {
    [$member, , $poll, $option] = reservedSetup();

    $poll->group->members()->detach($member);

    expect(feedOf($member))->not->toContain('poll-option-'.$option->slug);
});

it('F-34: answers of other people are not reserved for the token owner', function () {
    [, $owner, $members, $poll] = pollGroup(2);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    answerOption($option, [$members[1]], PollResponseStatus::Yes);
    $members[0]->enableCalendarFeed();

    expect(feedOf($members[0]))->not->toContain('poll-option-');
});

it('F-34: an option without a time is all-day, an option with a time uses floating times', function (?string $startsAt, ?string $endsAt, array $expected) {
    [$member, , , $option] = reservedSetup(optionAttributes: ['date' => '2027-03-12', 'starts_at' => $startsAt, 'ends_at' => $endsAt]);

    $block = feedBlock(feedOf($member), 'poll-option-'.$option->slug.'@');

    foreach ($expected as $line) {
        expect($block)->toContain($line);
    }

    expect($block)->not->toContain('TZID');
})->with([
    'all-day' => [null, null, ['DTSTART;VALUE=DATE:20270312', 'DTEND;VALUE=DATE:20270313']],
    'timed' => ['18:00', '19:00', ["DTSTART:20270312T180000\r\n", "DTEND:20270312T190000\r\n"]],
]);

it('F-34: the reserved summary follows the user\'s language', function (?string $language, string $prefix) {
    [$member, , , $option] = reservedSetup(pollAttributes: ['title' => 'Trip']);
    $member->update(['language' => $language]);
    $locale = app()->getLocale();

    expect(feedBlock(feedOf($member), 'poll-option-'.$option->slug.'@'))->toContain('SUMMARY:'.$prefix.'Trip')
        ->and(app()->getLocale())->toBe($locale);
})->with([
    'en' => ['en', 'Reserved: '],
    'de' => ['de', 'Reserviert: '],
    'default' => [null, 'Reserved: '],
]);

it('F-34: an option label is added to the reserved summary when set', function () {
    [$member, , , $labelled] = reservedSetup(pollAttributes: ['title' => 'Trip'], optionAttributes: ['label' => 'Evening']);
    $plain = PollOption::factory()->create(['poll_id' => $labelled->poll_id, 'label' => null]);
    answerOption($plain, [$member], PollResponseStatus::Yes);

    $body = feedOf($member);

    expect(feedBlock($body, 'poll-option-'.$labelled->slug.'@'))->toContain('SUMMARY:Reserved: Trip (Evening)')
        ->and(feedBlock($body, 'poll-option-'.$plain->slug.'@'))->toContain("SUMMARY:Reserved: Trip\r\n");
});

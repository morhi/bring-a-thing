<?php

use App\Enums\GroupRole;
use App\Enums\NotificationKind;
use App\Enums\PollResponseStatus;
use App\Mail\FixedDatesMail;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Create a group with an owner who organizes a date finder poll.
 *
 * @return array{group: Group, owner: User, poll: Poll}
 */
function fixedMailPoll(?string $ownerLanguage = null): array
{
    $owner = User::factory()->create(['language' => $ownerLanguage]);
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Gig date']);

    return compact('group', 'owner', 'poll');
}

/**
 * Add a group member who answered the option.
 *
 * @param  array<string, mixed>  $attributes
 */
function fixedMailResponder(Group $group, PollOption $option, ?PollResponseStatus $status, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $group->addMember($user, GroupRole::Member, now());

    if ($status !== null) {
        $option->responses()->create(['user_id' => $user->id, 'status' => $status]);
    }

    return $user;
}

/**
 * The addresses of every queued summary mail.
 *
 * @return array<int, string>
 */
function fixedMailRecipients(): array
{
    return Mail::queued(FixedDatesMail::class)
        ->map(fn (FixedDatesMail $mail) => $mail->to[0]['address'])
        ->sort()
        ->values()
        ->all();
}

it('F-30: fixing one date queues one mail to each accepted and pending participant with an email', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $yes = fixedMailResponder($group, $option, PollResponseStatus::Yes);
    $maybe = fixedMailResponder($group, $option, PollResponseStatus::Maybe);
    $no = fixedMailResponder($group, $option, PollResponseStatus::No);
    $none = fixedMailResponder($group, $option, null);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success', 'Date fixed');

    Mail::assertQueuedCount(2);
    Mail::assertNothingSent();
    expect(fixedMailRecipients())->toBe(collect([$yes->email, $maybe->email])->sort()->values()->all())
        ->and($poll->fresh()->isClosed())->toBeTrue()
        ->and($yes->fresh()->notifications()->get()->pluck('data.kind')->all())->toBe([NotificationKind::Fixed->value])
        ->and($owner->fresh()->notifications()->count())->toBe(0)
        ->and($no->fresh()->notifications()->count())->toBe(0)
        ->and($none->fresh()->notifications()->count())->toBe(0);
});

it('F-30: the acting user gets no mail even when they are a participant, and the poll organizer gets one when someone else fixes', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $option->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);
    $admin = fixedMailResponder($group, $option, PollResponseStatus::Yes);
    $group->members()->updateExistingPivot($admin->id, ['role' => GroupRole::Admin]);

    $this->actingAs($admin)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');

    Mail::assertQueuedCount(1);
    expect(fixedMailRecipients())->toBe([$owner->email]);
});

it('F-30: fixing several dates queues one summary mail per recipient that lists every date', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $late = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-05-20']);
    $early = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $middle = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-04-09']);
    $all = fixedMailResponder($group, $late, PollResponseStatus::Yes);
    $early->responses()->create(['user_id' => $all->id, 'status' => PollResponseStatus::Yes]);
    $middle->responses()->create(['user_id' => $all->id, 'status' => PollResponseStatus::Yes]);
    fixedMailResponder($group, $early, PollResponseStatus::Yes);
    fixedMailResponder($group, $late, PollResponseStatus::Maybe);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$late->slug, $early->slug, $middle->slug]])->assertSessionHas('success');

    Mail::assertQueuedCount(3);
    $dates = collect(['2027-03-12', '2027-04-09', '2027-05-20'])->map(fn (string $date) => Carbon::parse($date)->translatedFormat('l, j. F Y'));

    Mail::queued(FixedDatesMail::class)->each(function (FixedDatesMail $mail) use ($dates) {
        $html = $mail->render();
        $positions = $dates->map(fn (string $date) => strpos($html, $date));

        expect($positions->contains(false))->toBeFalse()
            ->and($positions->all())->toBe($positions->sort()->all());
    });
});

it('F-30: participants without an email address are skipped', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $member = fixedMailResponder($group, $option, PollResponseStatus::Yes);
    fixedMailResponder($group, $option, PollResponseStatus::Yes, ['email' => null]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');

    Mail::assertQueuedCount(1);
    expect(fixedMailRecipients())->toBe([$member->email]);
});

it('F-30: no mail is queued when no recipient remains', function (string $case) {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);

    match ($case) {
        'only the organizer' => $option->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]),
        'only without email' => fixedMailResponder($group, $option, PollResponseStatus::Yes, ['email' => null]),
        default => null,
    };

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');

    Mail::assertNothingQueued();
    expect($poll->fresh()->isClosed())->toBeTrue()
        ->and($option->fresh()->isFixed())->toBeTrue();
})->with(['no responses', 'only the organizer', 'only without email']);

it('F-30: each recipient\'s mail is rendered in their own language', function (?string $language, string $expected) {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll('de');
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    fixedMailResponder($group, $option, PollResponseStatus::Yes, ['language' => $language]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');

    Mail::assertQueued(FixedDatesMail::class, function (FixedDatesMail $mail) use ($expected) {
        $html = $mail->render();
        $line = trans_choice('The date has been fixed.|The dates have been fixed.', 1, [], $expected);

        expect($mail->locale)->toBe($expected)
            ->and($html)->toContain($line);
        $mail->assertHasSubject(__('Dates fixed: :title', ['title' => 'Gig date'], $expected));

        return true;
    });
})->with([
    'de' => ['de', 'de'],
    'en' => ['en', 'en'],
    'null falls back to the request locale' => [null, 'de'],
]);

it('F-30: two recipients with different languages in one fix each get their own locale', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $german = fixedMailResponder($group, $option, PollResponseStatus::Yes, ['language' => 'de']);
    $english = fixedMailResponder($group, $option, PollResponseStatus::Yes, ['language' => 'en']);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]]);

    Mail::assertQueued(FixedDatesMail::class, fn (FixedDatesMail $mail) => $mail->hasTo($german->email) && $mail->locale === 'de');
    Mail::assertQueued(FixedDatesMail::class, fn (FixedDatesMail $mail) => $mail->hasTo($english->email) && $mail->locale === 'en');
});

it('F-30: a rejected fix queues no mail', function (string $case) {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $other = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-13']);
    $member = fixedMailResponder($group, $option, PollResponseStatus::Yes);
    $other->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);

    $actor = $owner;
    $slug = $option->slug;

    if ($case === 'closed poll') {
        $poll->close();
    }

    if ($case === 'option of another poll') {
        $slug = PollOption::factory()->create(['poll_id' => Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id])->id])->slug;
    }

    if ($case === 'option already fixed') {
        $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]]);
        $poll->reopen();
        Mail::fake();
    }

    if ($case === 'no permission') {
        $actor = fixedMailResponder($group, $option, null);
    }

    $response = $this->actingAs($actor)->post(route('polls.fix', $poll), ['options' => [$slug]]);

    $case === 'no permission' ? $response->assertForbidden() : $response->assertSessionHasErrors('options');
    Mail::assertNothingQueued();
})->with(['closed poll', 'option of another poll', 'option already fixed', 'no permission']);

it('F-30: a participant who may not view the event gets no mail', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $stayed = fixedMailResponder($group, $option, PollResponseStatus::Yes);
    $removed = fixedMailResponder($group, $option, PollResponseStatus::Yes);
    $group->members()->detach($removed->id);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');

    Mail::assertQueuedCount(1);
    expect(fixedMailRecipients())->toBe([$stayed->email]);
});

it('F-30: a poll title cannot inject markdown or extra lines into the mail', function () {
    $mail = new FixedDatesMail("Gig\n\n[Verify your account](https://evil.example)", 'https://app.example/polls/x', collect());

    $html = $mail->render();

    expect($html)->not->toContain('href="https://evil.example"')
        ->and($mail->envelope()->subject)->not->toContain("\n");
});

it('F-30: a recipient sees the times in their own time zone', function (string $zone, string $expected) {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'poll' => $poll] = fixedMailPoll();
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => null, 'starts_at' => '2027-03-12 18:00:00', 'ends_at' => null]);
    $member = fixedMailResponder($group, $option, PollResponseStatus::Yes, ['timezone' => $zone]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');

    Mail::assertQueued(FixedDatesMail::class, function (FixedDatesMail $mail) use ($member, $expected) {
        expect($mail->hasTo($member->email))->toBeTrue()
            ->and($mail->render())->toContain($expected);

        return true;
    });
})->with([
    'Europe/Zurich' => ['Europe/Zurich', '19:00'],
    'America/New_York' => ['America/New_York', '13:00'],
]);

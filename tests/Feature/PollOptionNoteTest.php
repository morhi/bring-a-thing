<?php

use App\Enums\GroupRole;
use App\Enums\PollResponseStatus;
use App\Events\PollResponseUpdated;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner and a member, plus an open poll by the owner with two options.
 *
 * @return array{0: User, 1: User, 2: Poll, 3: PollOption, 4: PollOption}
 */
function notePoll(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    [$first, $second] = PollOption::factory()->count(2)->create(['poll_id' => $poll->id])->all();

    return [$owner, $member, $poll, $first, $second];
}

it('F-06: a respondent saves a note of 200 characters on their own yes answer', function () {
    [, $member, $poll, $first] = notePoll();
    $first->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);
    EventFacade::fake([PollResponseUpdated::class]);
    $note = str_repeat('a', 200);

    $this->actingAs($member)->put(route('polls.responses.note', [$poll, $first]), ['note' => $note])
        ->assertSessionHas('success', 'Note saved');

    expect($first->responses()->where('user_id', $member->id)->value('note'))->toBe($note);
    EventFacade::assertDispatched(PollResponseUpdated::class, fn (PollResponseUpdated $event) => $event->userId === $member->id && $event->optionId === $first->id);
});

it('F-06: a note on an option without an answer is refused', function () {
    [, $member, $poll, $first] = notePoll();

    $this->actingAs($member)->put(route('polls.responses.note', [$poll, $first]), ['note' => 'evenings only'])
        ->assertSessionHasErrors('note');

    expect($first->responses()->count())->toBe(0);
});

it('F-06: a note of 201 characters is refused', function () {
    [, $member, $poll, $first] = notePoll();
    $first->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);

    $this->actingAs($member)->put(route('polls.responses.note', [$poll, $first]), ['note' => str_repeat('a', 201)])
        ->assertSessionHasErrors('note');

    expect($first->responses()->value('note'))->toBeNull();
});

it('F-06: a note on a closed poll is refused', function () {
    [, $member, $poll, $first] = notePoll();
    $first->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);
    $poll->close();

    $this->actingAs($member)->put(route('polls.responses.note', [$poll, $first]), ['note' => 'evenings only'])
        ->assertSessionHasErrors('note');

    expect($first->responses()->value('note'))->toBeNull();
});

it('F-06: a note from someone outside the audience is refused', function () {
    [, , $poll, $first] = notePoll();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->put(route('polls.responses.note', [$poll, $first]), ['note' => 'evenings only'])
        ->assertNotFound();

    expect($first->responses()->count())->toBe(0);
});

it('F-06: other people see the note in the page data and changing the answer keeps it', function () {
    [$owner, $member, $poll, $first] = notePoll();
    $first->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);

    $this->actingAs($member)->put(route('polls.responses.note', [$poll, $first]), ['note' => 'evenings only'])->assertSessionHasNoErrors();
    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $first]), ['status' => 'maybe'])->assertSessionHasNoErrors();
    // Editing answers for someone else leaves their note alone
    $this->actingAs($owner)->post(route('polls.responses.store-for', [$poll, $first, $member]), ['status' => 'no'])->assertSessionHasNoErrors();

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.note_max_length', 200)
            ->where('page.respondents', fn ($respondents) => collect($respondents)->contains(
                fn ($row) => $row['user_id'] === $member->id && $row['notes'] === [$first->slug => 'evenings only'],
            )));
});

it('F-06: an empty note clears it', function () {
    [, $member, $poll, $first] = notePoll();
    $first->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);
    $first->responses()->where('user_id', $member->id)->update(['note' => 'evenings only']);

    $this->actingAs($member)->put(route('polls.responses.note', [$poll, $first]), ['note' => ''])
        ->assertSessionHas('success', 'Note removed');

    expect($first->responses()->where('user_id', $member->id)->value('note'))->toBeNull()
        ->and($first->responses()->where('user_id', $member->id)->value('status'))->toBe(PollResponseStatus::Yes);
});

it('F-06: withdrawing from the poll removes the notes with the answers', function () {
    [, $member, $poll, $first, $second] = notePoll();
    $first->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::Yes]);
    $second->responses()->create(['user_id' => $member->id, 'status' => PollResponseStatus::No]);
    $first->responses()->update(['note' => 'evenings only']);

    $this->actingAs($member)->delete(route('polls.withdraw', $poll))->assertRedirect();

    expect($first->responses()->count())->toBe(0)
        ->and($second->responses()->count())->toBe(0);
});

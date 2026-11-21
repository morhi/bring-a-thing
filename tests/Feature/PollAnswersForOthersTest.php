<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Events\PollResponseUpdated;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner, an admin and a plain member, plus an open poll by the owner with two options.
 *
 * @return array{0: Group, 1: User, 2: User, 3: User, 4: Poll, 5: PollOption, 6: PollOption}
 */
function answersForOthersPoll(): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    [$first, $second] = PollOption::factory()->count(2)->create(['poll_id' => $poll->id])->all();

    return [$group, $owner, $admin, $member, $poll, $first, $second];
}

it('F-06: an admin sets maybe for a member without answers and it counts in totals and the best option', function () {
    [, $owner, $admin, $member, $poll, $first, $second] = answersForOthersPoll();
    $second->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);
    $first->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);
    EventFacade::fake([PollResponseUpdated::class]);

    $this->actingAs($admin)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_edit_answers', true)
            ->where('page.respondents', fn ($respondents) => collect($respondents)->contains(fn ($row) => $row['user_id'] === $member->id && $row['answers'] === [] && $row['in_audience'])));

    $this->actingAs($admin)->post(route('polls.responses.store-for', [$poll, $first, $member]), ['status' => 'maybe'])
        ->assertSessionHas('success', 'Answer saved for '.$member->name);

    expect($first->responses()->where('user_id', $member->id)->value('status'))->toBe(PollResponseStatus::Maybe);
    EventFacade::assertDispatched(PollResponseUpdated::class, fn (PollResponseUpdated $event) => $event->userId === $member->id && $event->optionId === $first->id);

    $this->actingAs($member)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.poll.options', fn ($options) => collect($options)->firstWhere('slug', $first->slug)['maybe_count'] === 1)
            ->where('page.permissions.can_edit_answers', false));

    // A no for the member on the second option breaks the yes tie in favour of the first
    $this->actingAs($admin)->post(route('polls.responses.store-for', [$poll, $second, $member]), ['status' => 'no'])->assertSessionHasNoErrors();

    expect($poll->fresh()->bestOptions()->pluck('id')->all())->toBe([$first->id]);
});

it('F-06: a member without the admin role is refused', function () {
    [, $owner, , $member, $poll, $first] = answersForOthersPoll();

    $this->actingAs($member)->post(route('polls.responses.store-for', [$poll, $first, $owner]), ['status' => 'yes'])->assertForbidden();

    expect($first->responses()->count())->toBe(0);
});

it('F-06: setting answers for others is refused on a closed poll', function () {
    [, $owner, , $member, $poll, $first] = answersForOthersPoll();
    $poll->close();

    $this->actingAs($owner)->post(route('polls.responses.store-for', [$poll, $first, $member]), ['status' => 'yes'])->assertForbidden();

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_edit_answers', false));
    expect($first->responses()->count())->toBe(0);
});

it('F-06: a target outside the audience or withdrawn is refused', function () {
    [, $owner, $admin, $member, $poll, $first] = answersForOthersPoll();
    $outsider = User::factory()->create();
    $poll->withdrawals()->create(['user_id' => $member->id]);

    $this->actingAs($admin)->post(route('polls.responses.store-for', [$poll, $first, $outsider]), ['status' => 'yes'])->assertSessionHasErrors('status');
    $this->actingAs($owner)->post(route('polls.responses.store-for', [$poll, $first, $member]), ['status' => 'yes'])->assertSessionHasErrors('status');

    expect($first->responses()->count())->toBe(0);
});

it('F-06: the organizer of a standalone poll sets answers for a link guest', function () {
    $organizer = User::factory()->create();
    $guest = User::factory()->create();
    $poll = Poll::factory()->create(['group_id' => null, 'organizer_id' => $organizer->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $poll->accessGrants()->create(['user_id' => $guest->id]);

    $this->actingAs($organizer)->post(route('polls.responses.store-for', [$poll, $option, $guest]), ['status' => 'yes'])
        ->assertSessionHasNoErrors();
    $this->actingAs($guest)->post(route('polls.responses.store-for', [$poll, $option, $organizer]), ['status' => 'yes'])->assertForbidden();

    expect($option->responses()->where('user_id', $guest->id)->value('status'))->toBe(PollResponseStatus::Yes);
    expect($option->responses()->where('user_id', $organizer->id)->exists())->toBeFalse();
});

it('F-06: on a follow-up poll the target must be an accepted participant', function () {
    [$group, $owner, , $member] = answersForOthersPoll();
    $declined = User::factory()->create();
    $group->addMember($declined, GroupRole::Member, now());
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $event->participations()->create(['user_id' => $member->id, 'status' => ParticipationStatus::Accepted]);
    $event->participations()->create(['user_id' => $declined->id, 'status' => ParticipationStatus::Declined]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'event_id' => $event->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($owner)->post(route('polls.responses.store-for', [$poll, $option, $member]), ['status' => 'maybe'])->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('polls.responses.store-for', [$poll, $option, $declined]), ['status' => 'maybe'])->assertSessionHasErrors('status');

    expect($option->responses()->pluck('user_id')->all())->toBe([$member->id]);
});

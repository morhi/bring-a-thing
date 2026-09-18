<?php

use App\Enums\GroupRole;
use App\Enums\PollResponseStatus;
use App\Enums\PollType;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

/**
 * Create a group-attached roster with an attendance poll on the same group,
 * plus the owner and given members joined to the group.
 *
 * @param  array<int, User>  $members
 * @return array{0: Roster, 1: PollOption, 2: Group}
 */
function rosterWithAttendancePoll(User $owner, array $members = []): array
{
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $poll = Poll::factory()->create(['type' => PollType::Attendance, 'group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    return [$roster, $option, $group];
}

it('lets the roster owner link an attendance day', function () {
    $owner = User::factory()->create();
    [$roster, $option] = rosterWithAttendancePoll($owner);

    $response = $this->actingAs($owner)->patch(route('rosters.update', $roster), [
        'title' => $roster->title,
        'attendance_poll_option_id' => $option->id,
    ]);

    $response->assertRedirect();
    expect($roster->fresh()->attendance_poll_option_id)->toBe($option->id);
});

it('lets a group admin link an attendance day but forbids a plain member', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    [$roster, $option, $group] = rosterWithAttendancePoll($owner, [$admin, $member]);
    $group->members()->updateExistingPivot($admin, ['role' => GroupRole::Admin]);

    $this->actingAs($admin)->patch(route('rosters.update', $roster), [
        'title' => $roster->title,
        'attendance_poll_option_id' => $option->id,
    ])->assertRedirect();
    expect($roster->fresh()->attendance_poll_option_id)->toBe($option->id);

    $this->actingAs($member)->patch(route('rosters.update', $roster), [
        'title' => $roster->title,
        'attendance_poll_option_id' => $option->id,
    ])->assertForbidden();
});

it('rejects an attendance option belonging to a different group', function () {
    $owner = User::factory()->create();
    [$roster] = rosterWithAttendancePoll($owner);
    $otherGroupOption = PollOption::factory()->create([
        'poll_id' => Poll::factory()->create(['type' => PollType::Attendance])->id,
    ]);

    $response = $this->actingAs($owner)->patch(route('rosters.update', $roster), [
        'title' => $roster->title,
        'attendance_poll_option_id' => $otherGroupOption->id,
    ]);

    $response->assertSessionHasErrors('attendance_poll_option_id');
});

it('rejects a date-finder poll option as an attendance link', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $dateFinderOption = PollOption::factory()->create([
        'poll_id' => Poll::factory()->create(['type' => PollType::DateFinder, 'group_id' => $group->id])->id,
    ]);

    $response = $this->actingAs($owner)->patch(route('rosters.update', $roster), [
        'title' => $roster->title,
        'attendance_poll_option_id' => $dateFinderOption->id,
    ]);

    $response->assertSessionHasErrors('attendance_poll_option_id');
});

it('resolves the effective attendance day from the item, falling back to the roster', function () {
    $owner = User::factory()->create();
    [$roster, $rosterOption] = rosterWithAttendancePoll($owner);
    $roster->update(['attendance_poll_option_id' => $rosterOption->id]);
    $itemOption = PollOption::factory()->create(['poll_id' => $rosterOption->poll_id]);

    $itemWithoutOwnLink = RosterItem::factory()->create(['roster_id' => $roster->id]);
    expect($itemWithoutOwnLink->effective_attendance_poll_option->id)->toBe($rosterOption->id);

    $itemWithOwnLink = RosterItem::factory()->create([
        'roster_id' => $roster->id,
        'attendance_poll_option_id' => $itemOption->id,
    ]);
    expect($itemWithOwnLink->effective_attendance_poll_option->id)->toBe($itemOption->id);
});

it('never blocks claiming regardless of the claimant’s attendance response', function (?string $status) {
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    [$roster, $option] = rosterWithAttendancePoll($owner, [$claimant]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'attendance_poll_option_id' => $option->id]);

    if ($status !== null) {
        $option->responses()->create(['user_id' => $claimant->id, 'status' => PollResponseStatus::from($status)]);
    }

    $response = $this->actingAs($claimant)->post(route('rosters.items.claim.store', [$roster, $item]));

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $claimant->id]);
})->with([
    'marked yes' => ['yes'],
    'marked maybe' => ['maybe'],
    'marked no' => ['no'],
    'no response at all' => [null],
]);

<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollType;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/**
 * Create a group with an owner, an admin and a plain member.
 *
 * @return array{0: Group, 1: User, 2: User, 3: User}
 */
function criteriaGroup(): array
{
    [$owner, $admin, $member] = User::factory()->count(3)->create()->all();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $admin, $member];
}

it('F-14: without "Members can add items" a member sees no add action, an admin does', function () {
    [$group, $owner, $admin, $member] = criteriaGroup();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'members_can_add_items' => false]);

    $this->actingAs($member)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_add_items', false));
    $this->actingAs($admin)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_add_items', true));
    $this->actingAs($member)->post(route('rosters.items.store', $roster), ['name' => 'Cake'])->assertForbidden();

    $roster->update(['members_can_add_items' => true]);

    $this->actingAs($member)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_add_items', true));
});

it('F-14: an item dated 14.03. in a list dated 12.03. appears under 14.03.', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'date' => '2027-03-12']);
    RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Cake', 'date' => '2027-03-14']);
    RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Chairs']);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page
            ->has('page.items', 2)
            ->where('page.items', fn ($items) => collect($items)->firstWhere('name', 'Cake')['effective_date'] === '2027-03-14'
                && collect($items)->firstWhere('name', 'Chairs')['effective_date'] === '2027-03-12'));
});

it('F-16: a field "Allergens" appears in every item form of its list and not in other lists', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $other = Roster::factory()->create(['owner_id' => $owner->id]);
    $field = CustomField::factory()->create(['roster_id' => $roster->id, 'name' => 'Allergens']);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->has('page.custom_fields', 1)->where('page.custom_fields.0.name', 'Allergens'));
    $this->actingAs($owner)->get(route('rosters.show', $other))
        ->assertInertia(fn (Assert $page) => $page->has('page.custom_fields', 0));
    $this->actingAs($owner)->post(route('rosters.items.store', $other), ['name' => 'Cake', 'custom_fields' => [$field->id => 'nuts']])
        ->assertSessionHasErrors('custom_fields');
});

it('F-17: duplicating a list with three items and two claims gives three items, no claims, no date, plus custom fields', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'date' => '2027-03-12']);
    CustomField::factory()->create(['roster_id' => $roster->id, 'name' => 'Allergens']);
    $items = RosterItem::factory()->count(3)->create(['roster_id' => $roster->id, 'date' => '2027-03-14']);
    $items[0]->claims()->create(['user_id' => $owner->id, 'quantity' => null]);
    $items[1]->claims()->create(['user_id' => $owner->id, 'quantity' => null]);

    $this->actingAs($owner)->post(route('rosters.duplicate', $roster))->assertRedirect();

    $copy = Roster::query()->whereKeyNot($roster->id)->sole();
    expect($copy->date)->toBeNull()
        ->and($copy->items)->toHaveCount(3)
        ->and($copy->items->whereNotNull('date'))->toHaveCount(0)
        ->and($copy->items->sum(fn (RosterItem $item) => $item->claims()->count()))->toBe(0)
        ->and($copy->customFields->pluck('name')->all())->toBe(['Allergens'])
        ->and($roster->items()->withCount('claims')->get()->sum('claims_count'))->toBe(2);
});

it('F-24: after switching to German in settings, a login on another device shows German', function () {
    $user = User::factory()->language('en')->create();

    $this->actingAs($user)->patch(route('language.update'), ['language' => 'de'])->assertRedirect();

    // A different device has its own session and an English browser.
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id]);

    $this->withHeader('Accept-Language', 'en')->get($url)->assertRedirect(route('dashboard'));
    $this->withHeader('Accept-Language', 'en')->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'de'));
});

it('F-22: a person without groups and lists gets an empty dashboard with both entry points to fill', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('groups', 0)
            ->has('lists', 0)
            ->where('counts.groups', 0)
            ->where('counts.lists', 0));
});

it('F-22: nothing waiting gives an empty "Waiting for you" so the page can show "All caught up."', function () {
    [, , , $member] = criteriaGroup();

    $this->actingAs($member)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('waiting', 0)->where('counts.waiting', 0)->has('groups', 1));
});

it('F-04: a group without other members offers the owner the invite form', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $this->actingAs($owner)->get(route('groups.show', $group))
        ->assertInertia(fn (Assert $page) => $page->has('page.members', 1)->where('page.permissions.can_invite', true));
});

it('F-14: an empty list offers the owner "Add first item" and a member without the right only the note', function () {
    [$group, $owner, , $member] = criteriaGroup();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'members_can_add_items' => false]);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->has('page.items', 0)->where('page.permissions.can_add_items', true)->where('page.is_all_taken', false));
    $this->actingAs($member)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->has('page.items', 0)->where('page.permissions.can_add_items', false)->where('page.is_all_taken', false));
});

it('F-15: a list shows "All taken" once every item is fully claimed and not before', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $chairs = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 2]);
    $task = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $chairs->claims()->create(['user_id' => $owner->id, 'quantity' => 2]);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->where('page.is_all_taken', false));

    $task->claims()->create(['user_id' => $owner->id, 'quantity' => null]);

    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->where('page.is_all_taken', true));
});

it('F-08: a closed poll shows the fixed options highlighted and the others not', function () {
    [$group, $owner] = criteriaGroup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $fixed = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-03']);
    PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-10']);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$fixed->slug]]);

    $this->actingAs($owner)->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.poll.is_closed', true)
            ->where('page.poll.options.0.is_fixed', true)
            ->where('page.poll.options.0.can_remove', false)
            ->where('page.poll.options.1.is_fixed', false)
            ->where('page.poll.options.1.can_remove', false));
});

it('F-10: moving an event resets acceptances to pending and keeps the claims', function () {
    [$group, $owner, , $member] = criteriaGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'date' => '2027-03-12']);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $member->id]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $item->claims()->create(['user_id' => $member->id, 'quantity' => null]);

    $this->actingAs($owner)->patch(route('events.update', $event), [
        'title' => $event->title, 'description' => null, 'location' => null,
        'date' => '2027-03-19', 'starts_at' => null, 'end_date' => null, 'ends_at' => null,
    ])->assertRedirect(route('events.show', $event));

    expect($event->participations()->where('user_id', $member->id)->value('status'))->toBe(ParticipationStatus::Pending)
        ->and($item->claims()->count())->toBe(1);
});

it('F-10: an event without acceptances lets managers create lists and follow-up polls and shows nobody accepted', function () {
    [$group, $owner] = criteriaGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($owner)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.accepted_count', 0)
            ->where('page.permissions.can_create_list', true)
            ->where('page.permissions.can_create_follow_up_poll', true));
});

it('F-11: a past event keeps its lists readable for an accepted participant', function () {
    [$group, $owner, , $member] = criteriaGroup();
    $event = Event::factory()->past()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $member->id]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);

    $this->actingAs($member)->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_respond', false)->has('page.lists', 1));
    $this->actingAs($member)->get(route('rosters.show', $roster))->assertOk();
});

it('F-12: a child event still lets managers create lists', function () {
    [$group, $owner] = criteriaGroup();
    $parent = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $child = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $parent->id]);

    $this->actingAs($owner)->get(route('events.show', $child))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_create_list', true)
            ->where('page.permissions.can_create_follow_up_poll', false));
});

it('F-18: the attendance field stays hidden without an attendance poll in the group and appears with one', function () {
    [$group, $owner] = criteriaGroup();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $this->actingAs($owner)->get(route('rosters.edit', $roster))
        ->assertInertia(fn (Assert $page) => $page->has('form.attendance_options', 0));
    $this->actingAs($owner)->get(route('rosters.show', $roster))
        ->assertInertia(fn (Assert $page) => $page->has('page.attendance_options', 0));

    $poll = Poll::factory()->create(['type' => PollType::Attendance, 'group_id' => $group->id, 'organizer_id' => $owner->id]);
    PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($owner)->get(route('rosters.edit', $roster))
        ->assertInertia(fn (Assert $page) => $page->has('form.attendance_options', 1));
});

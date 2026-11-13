<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Open the group page as the user and return the slugs per item section.
 *
 * @return array{polls: array<int, string>, events: array<int, string>, lists: array<int, string>}
 */
function guestPageSlugs(Group $group, User $user): array
{
    $response = test()->actingAs($user)->get(route('groups.show', $group))->assertOk();

    return collect(['polls', 'events', 'lists'])
        ->mapWithKeys(fn (string $section) => [$section => collect($response->inertiaProps("page.{$section}"))->pluck('slug')->all()])
        ->all();
}

/**
 * Serialize every prop of an Inertia response, to check that a value never reaches the browser.
 */
function guestPageJson(TestResponse $response): string
{
    return json_encode($response->inertiaProps());
}

it('F-26: an event link guest opens the group page with the event and its sub-items', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = dashboardEvent($group, $owner, $guest, ParticipationStatus::Accepted);
    grantOn($event, $guest);
    $child = dashboardEvent($group, $owner, null, null, ['parent_event_id' => $event->id, 'date' => $event->date->copy()->addDay()->format('Y-m-d')]);
    $followUp = dashboardPoll($group, $owner, 1, null, 0, ['event_id' => $event->id]);
    $list = dashboardList($owner, ['event_id' => $event->id]);

    $this->actingAs($guest)->get(route('groups.show', $group))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Groups/Show')
        ->where('page.name', $group->name)
        ->where('page.is_guest', true));

    expect(guestPageSlugs($group, $guest))->toEqualCanonicalizing([
        'polls' => [$followUp->slug],
        'events' => [$event->slug, $child->slug],
        'lists' => [$list->slug],
    ]);
});

it('F-27: a poll link guest sees the poll, its fixed event and that event\'s child event', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $poll = dashboardPoll($group, $owner, 1);
    grantOn($poll, $guest);
    $fixed = dashboardEvent($group, $owner, null, null, ['origin_poll_option_id' => $poll->options()->first()->id]);
    $child = dashboardEvent($group, $owner, null, null, ['parent_event_id' => $fixed->id]);
    dashboardEvent($group, $owner);
    dashboardPoll($group, $owner, 1);

    $slugs = guestPageSlugs($group, $guest);

    expect($slugs['polls'])->toBe([$poll->slug])
        ->and($slugs['events'])->toEqualCanonicalizing([$fixed->slug, $child->slug])
        ->and($slugs['lists'])->toBe([]);
});

it('F-26: follow-up polls and lists follow the participation rules', function (?ParticipationStatus $status, bool $pollShown, bool $listShown) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = dashboardEvent($group, $owner, $guest, $status);
    grantOn($event, $guest);
    $followUp = dashboardPoll($group, $owner, 1, null, 0, ['event_id' => $event->id]);
    $list = dashboardList($owner, ['event_id' => $event->id]);

    $slugs = guestPageSlugs($group, $guest);

    expect($slugs['polls'])->toBe($pollShown ? [$followUp->slug] : [])
        ->and($slugs['lists'])->toBe($listShown ? [$list->slug] : []);
})->with([
    'no participation' => [null, false, false],
    'pending' => [ParticipationStatus::Pending, false, true],
    'accepted' => [ParticipationStatus::Accepted, true, true],
    'declined' => [ParticipationStatus::Declined, false, false],
]);

it('F-26: the restricted page carries no members, settings, invite link or create actions', function () {
    $owner = User::factory()->create(['name' => 'Olivia Owner', 'email' => 'olivia.owner@example.test']);
    $member = User::factory()->create(['name' => 'Max Member', 'email' => 'max.member@example.test']);
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $group->enableInviteLink();
    $guest = User::factory()->create();
    grantOn(dashboardEvent($group, $owner), $guest);

    $response = $this->actingAs($guest)->get(route('groups.show', $group))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('page.own_role', null)
        ->has('page.members', 0)
        ->where('page.invite_link', null)
        ->has('page.friends', 0)
        ->where('page.permissions', ['can_manage_settings' => false, 'can_invite' => false, 'can_create' => false]));
    $json = guestPageJson($response);

    foreach (['Olivia Owner', 'olivia.owner@example.test', 'Max Member', 'max.member@example.test', $group->fresh()->invite_token] as $hidden) {
        expect($json)->not->toContain($hidden);
    }
});

it('F-26: hidden items never reach the props', function (Closure $hide) {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $event = dashboardEvent($group, $owner, $guest, ParticipationStatus::Accepted);
    grantOn($event, $guest);
    $hide($group, $owner, 'Secret hidden item');

    $response = $this->actingAs($guest)->get(route('groups.show', $group))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('page.polls', 0)
        ->has('page.events', 1)
        ->has('page.lists', 0));

    expect(guestPageJson($response))->not->toContain('Secret hidden item');
})->with([
    'sibling event' => [fn ($group, User $owner, string $title) => dashboardEvent($group, $owner, null, null, ['title' => $title])],
    'sibling poll' => [fn ($group, User $owner, string $title) => dashboardPoll($group, $owner, 1, null, 0, ['title' => $title])],
    'group list' => [fn ($group, User $owner, string $title) => dashboardList($owner, ['group_id' => $group->id, 'title' => $title])],
    'follow-up poll of a sibling event' => [fn ($group, User $owner, string $title) => dashboardPoll($group, $owner, 1, null, 0, ['title' => $title, 'event_id' => dashboardEvent($group, $owner)->id])],
]);

it('F-26: a person without access gets "Not found"', function (Closure $setup) {
    [$group, $owner] = dashboardGroup();
    $person = User::factory()->create();
    $setup($group, $owner, $person);

    $this->actingAs($person)->get(route('groups.show', $group))->assertNotFound();
})->with([
    'no grants' => [fn () => null],
    'grant in another group' => [function ($group, User $owner, User $person) {
        [$otherGroup, $otherOwner] = dashboardGroup();
        grantOn(dashboardEvent($otherGroup, $otherOwner), $person);
    }],
    'revoked grant' => [function ($group, User $owner, User $person) {
        $event = dashboardEvent($group, $owner);
        grantOn($event, $person);
        $event->accessGrants()->where('user_id', $person->id)->delete();
    }],
]);

it('F-26: joining as a member turns the restricted page into the full page', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    grantOn(dashboardEvent($group, $owner), $guest);

    $this->actingAs($guest)->get(route('groups.show', $group))->assertInertia(fn (Assert $page) => $page
        ->where('page.is_guest', true)
        ->has('page.members', 0));

    $group->addMember($guest, GroupRole::Member, now());

    $this->actingAs($guest)->get(route('groups.show', $group))->assertInertia(fn (Assert $page) => $page
        ->where('page.is_guest', false)
        ->where('page.own_role', 'member')
        ->has('page.members', 3)
        ->where('page.permissions', ['can_manage_settings' => false, 'can_invite' => false, 'can_create' => true]));
});

it('F-26: the restricted page needs no additional queries for more grants and items', function () {
    [$group, $owner] = dashboardGroup();
    $guest = User::factory()->create();
    $queryCount = function () use ($group, $guest) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($guest)->get(route('groups.show', $group))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $populate = function (int $times) use ($group, $owner, $guest) {
        foreach (range(1, $times) as $ignored) {
            $event = dashboardEvent($group, $owner, $guest, ParticipationStatus::Accepted);
            grantOn($event, $guest);
            dashboardEvent($group, $owner, null, null, ['parent_event_id' => $event->id]);
            dashboardPoll($group, $owner, 2, $guest, 1, ['event_id' => $event->id]);
            grantOn(dashboardPoll($group, $owner, 2, $guest, 1), $guest);
            dashboardList($owner, ['event_id' => $event->id], $guest);
            EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $owner->id, 'status' => ParticipationStatus::Accepted]);
        }
    };

    $populate(1);
    $small = $queryCount();
    $populate(4);

    expect($queryCount())->toBe($small);
});

it('F-26: a guest cannot reach mutating group routes', function (string $method, Closure $request, int $status) {
    [$group, $owner, $member] = dashboardGroup();
    $group->enableInviteLink();
    $guest = User::factory()->create();
    grantOn(dashboardEvent($group, $owner), $guest);
    $snapshot = fn () => [
        $group->fresh()->only(['name', 'invite_token', 'invite_link_locked']),
        $group->members()->get()->map(fn (User $user) => [$user->id, $user->pivot->role])->all(),
        Poll::query()->count(),
        Event::query()->count(),
        Roster::query()->count(),
    ];
    $before = $snapshot();

    [$url, $payload] = $request($group, $member);

    $this->actingAs($guest)->json($method, $url, $payload)->assertStatus($status);

    expect($snapshot())->toBe($before);
})->with([
    'groups.update' => ['patch', fn ($group, User $member) => [route('groups.update', $group), ['name' => 'Hijacked']], 403],
    'groups.destroy' => ['delete', fn ($group, User $member) => [route('groups.destroy', $group), []], 403],
    'groups.invite' => ['post', fn ($group, User $member) => [route('groups.invite', $group), ['invites' => [['email' => 'new@example.test']]]], 403],
    'invite link store' => ['post', fn ($group, User $member) => [route('groups.invite-link.store', $group), []], 403],
    'invite link update' => ['patch', fn ($group, User $member) => [route('groups.invite-link.update', $group), ['locked' => true]], 403],
    'invite link regenerate' => ['post', fn ($group, User $member) => [route('groups.invite-link.regenerate', $group), []], 403],
    'invite link destroy' => ['delete', fn ($group, User $member) => [route('groups.invite-link.destroy', $group), []], 403],
    'groups.members.update' => ['patch', fn ($group, User $member) => [route('groups.members.update', [$group, $member]), ['role' => 'admin']], 403],
    'groups.members.destroy' => ['delete', fn ($group, User $member) => [route('groups.members.destroy', [$group, $member]), []], 403],
    'groups.edit' => ['get', fn ($group, User $member) => [route('groups.edit', $group), []], 404],
    'polls.create' => ['get', fn ($group, User $member) => [route('polls.create', $group), []], 403],
    'polls.store' => ['post', fn ($group, User $member) => [route('polls.store', $group), ['title' => 'Hijacked poll', 'type' => 'date', 'options' => [['date' => now()->addWeek()->format('Y-m-d')]]]], 403],
    'events.create' => ['get', fn ($group, User $member) => [route('events.create', $group), []], 403],
    'events.store' => ['post', fn ($group, User $member) => [route('events.store', $group), ['title' => 'Hijacked event', 'date' => now()->addWeek()->format('Y-m-d')]], 403],
    'rosters.create with group' => ['get', fn ($group, User $member) => [route('rosters.create', ['group' => $group->slug]), []], 403],
    'rosters.store with group_id' => ['post', fn ($group, User $member) => [route('rosters.store'), ['title' => 'Hijacked list', 'group_id' => $group->id]], 403],
]);

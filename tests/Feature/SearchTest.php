<?php

use App\Data\SearchData;
use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;

/**
 * Create a group with the given person as plain member.
 */
function searchGroup(User $member, string $name = 'Band'): Group
{
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id, 'name' => $name]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    return $group;
}

/**
 * Collect the result titles per section type of a search response.
 *
 * @return array<string, array<int, string>>
 */
function searchTitles(array $response): array
{
    return collect($response['sections'])
        ->mapWithKeys(fn (array $section) => [$section['type'] => array_column($section['results'], 'title')])
        ->all();
}

it('F-25: requires a login', function () {
    $this->getJson(route('search', ['q' => 'gig']))->assertUnauthorized();
});

it('F-25: an empty or one-letter query returns no records', function (string $query) {
    $user = User::factory()->create();
    searchGroup($user, 'Gig crew');

    $this->actingAs($user)->getJson(route('search', ['q' => $query]))
        ->assertOk()
        ->assertJsonPath('sections', []);
})->with(['empty' => '', 'one letter' => 'g']);

it('F-25: finds groups, polls, events and lists by title with translated headings and urls', function () {
    $user = User::factory()->create();
    $group = searchGroup($user, 'Summer gig crew');
    $poll = Poll::factory()->create(['group_id' => $group->id, 'title' => 'Gig date']);
    $event = Event::factory()->create(['group_id' => $group->id, 'title' => 'Gig at the park', 'date' => '2027-03-14']);
    $roster = Roster::factory()->create(['group_id' => $group->id, 'title' => 'Gig snacks']);
    Roster::factory()->create(['group_id' => $group->id, 'title' => 'Rehearsal snacks']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'GIG']))->assertOk();

    expect(searchTitles($response->json()))->toBe([
        'groups' => ['Summer gig crew'],
        'polls' => ['Gig date'],
        'events' => ['Gig at the park'],
        'lists' => ['Gig snacks'],
    ]);
    $response
        ->assertJsonPath('sections.0.label', 'Groups')
        ->assertJsonPath('sections.1.results.0.url', route('polls.show', $poll))
        ->assertJsonPath('sections.2.results.0.url', route('events.show', $event))
        ->assertJsonPath('sections.2.results.0.context', '14 Mar 2027 · Summer gig crew')
        ->assertJsonPath('sections.3.results.0.url', route('rosters.show', $roster));
});

it('F-25: headings follow the interface language', function () {
    $user = User::factory()->create(['language' => 'de']);
    searchGroup($user, 'Gig crew');

    $this->actingAs($user)->getJson(route('search', ['q' => 'gig']))
        ->assertJsonPath('sections.0.label', 'Gruppen');
});

it('F-25: never returns records of groups the person is not a member of', function () {
    $user = User::factory()->create();
    searchGroup($user, 'Mine');
    $foreign = searchGroup(User::factory()->create(), 'Foreign gig group');
    Poll::factory()->create(['group_id' => $foreign->id, 'title' => 'Foreign gig poll']);
    Event::factory()->create(['group_id' => $foreign->id, 'title' => 'Foreign gig event']);
    Roster::factory()->create(['group_id' => $foreign->id, 'title' => 'Foreign gig list']);
    Roster::factory()->create(['title' => 'Someone else gig list']);

    $this->actingAs($user)->getJson(route('search', ['q' => 'gig']))
        ->assertOk()
        ->assertJsonPath('sections', []);
});

it('F-25: hides event lists and follow-up polls from members who do not take part', function () {
    $user = User::factory()->create();
    $group = searchGroup($user);
    $event = Event::factory()->create(['group_id' => $group->id, 'title' => 'Concert']);
    Roster::factory()->create(['event_id' => $event->id, 'title' => 'Gig gear']);
    Poll::factory()->create(['group_id' => $group->id, 'event_id' => $event->id, 'title' => 'Gig rehearsals']);

    $this->actingAs($user)->getJson(route('search', ['q' => 'gig']))->assertJsonPath('sections', []);

    EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => ParticipationStatus::Accepted]);

    expect(searchTitles($this->actingAs($user)->getJson(route('search', ['q' => 'gig']))->json()))->toBe([
        'polls' => ['Gig rehearsals'],
        'lists' => ['Gig gear'],
    ]);
});

it('F-25: finds standalone lists the person owns or claims on', function () {
    $user = User::factory()->create();
    Roster::factory()->create(['owner_id' => $user->id, 'title' => 'My gig list']);
    $claimed = Roster::factory()->create(['title' => 'Claimed gig list']);
    $item = RosterItem::factory()->create(['roster_id' => $claimed->id]);
    RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'gig list']))->assertOk();

    expect(searchTitles($response->json()))->toBe(['lists' => ['Claimed gig list', 'My gig list']])
        ->and($response->json('sections.0.results.1.context'))->toBe('Standalone list');
});

it('F-25: returns at most five records per type', function () {
    $user = User::factory()->create();
    $group = searchGroup($user);
    Poll::factory()->count(7)->create(['group_id' => $group->id, 'title' => 'Gig poll']);
    Roster::factory()->count(7)->create(['group_id' => $group->id, 'title' => 'Gig list']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'gig']))->assertOk();

    expect($response->json('sections.0.results'))->toHaveCount(SearchData::LIMIT)
        ->and($response->json('sections.1.results'))->toHaveCount(SearchData::LIMIT);
});

it('F-25: treats LIKE wildcards in the query literally', function () {
    $user = User::factory()->create();
    $group = searchGroup($user);
    Roster::factory()->create(['group_id' => $group->id, 'title' => '50% off drinks']);
    Roster::factory()->create(['group_id' => $group->id, 'title' => '50 chairs']);

    expect(searchTitles($this->actingAs($user)->getJson(route('search', ['q' => '50%']))->json()))
        ->toBe(['lists' => ['50% off drinks']]);
});

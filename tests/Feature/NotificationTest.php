<?php

use App\Enums\GroupRole;
use App\Enums\NotificationKind;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use App\Notifications\DateFixed;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner and two plain members.
 *
 * @return array{group: Group, owner: User, first: User, second: User}
 */
function notifGroup(): array
{
    $owner = User::factory()->create(['name' => 'Olivia']);
    $first = User::factory()->create(['name' => 'Finn']);
    $second = User::factory()->create(['name' => 'Sara']);
    $group = Group::factory()->create(['owner_id' => $owner->id, 'name' => 'Band']);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($first, GroupRole::Member, now());
    $group->addMember($second, GroupRole::Member, now());

    return compact('group', 'owner', 'first', 'second');
}

/**
 * Create an event in the group with the given participation per user.
 *
 * @param  array<int, array{0: User, 1: ParticipationStatus}>  $participants
 */
function notifEvent(Group $group, User $organizer, array $participants, array $attributes = []): Event
{
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id, 'title' => 'Gig', 'date' => '2027-03-12', ...$attributes]);

    foreach ($participants as [$user, $status]) {
        EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => $status]);
    }

    return $event;
}

/**
 * The kinds of the user's stored notifications.
 *
 * @return array<int, string>
 */
function notifKinds(User $user): array
{
    return $user->fresh()->notifications()->get()->pluck('data.kind')->all();
}

it('F-23: after fixing a date, the bell shows an unread notification to every accepted person', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Gig date']);
    $option = PollOption::factory()->create(['poll_id' => $poll->id, 'date' => '2027-03-12']);
    $option->responses()->create(['user_id' => $first->id, 'status' => PollResponseStatus::Yes]);
    $option->responses()->create(['user_id' => $owner->id, 'status' => PollResponseStatus::Yes]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success', 'Date fixed');

    expect(notifKinds($first))->toBe([NotificationKind::Fixed->value])
        ->and(notifKinds($owner))->toBe([])
        ->and(notifKinds($second))->toBe([]);

    $this->actingAs($first)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 1));
});

it('F-23: "Mark all as read" sets the counter to zero', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $this->actingAs($owner)->post(route('events.store', $group), ['title' => 'Gig', 'date' => '2027-03-12']);
    $this->actingAs($owner)->post(route('events.store', $group), ['title' => 'Rehearsal', 'date' => '2027-03-13']);

    $this->actingAs($first)->getJson(route('notifications.unread'))->assertExactJson(['unread' => 2]);

    $this->actingAs($first)->postJson(route('notifications.read-all'))->assertOk()->assertExactJson(['unread' => 0]);

    $this->actingAs($first)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0));
});

it('F-23: an invitation to a group notifies the invited person', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner] = notifGroup();

    $this->actingAs($owner)->post(route('groups.invite', $group), ['invites' => [['name' => 'Ina', 'email' => 'ina@example.com']]]);

    $invitee = User::query()->where('email', 'ina@example.com')->firstOrFail();
    $notification = $invitee->notifications()->firstOrFail();

    expect($notification->data['kind'])->toBe('invited')
        ->and($notification->data['params'])->toBe(['name' => 'Olivia', 'group' => 'Band'])
        ->and(notifKinds($owner))->toBe([]);
});

it('F-23: re-inviting an existing member sends no second invitation notice', function () {
    Mail::fake();
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();

    $this->actingAs($owner)->post(route('groups.invite', $group), ['invites' => [['name' => 'Finn', 'email' => $first->email]]]);

    expect(notifKinds($first))->toBe([]);
});

it('F-23: a new poll notifies every member except its organizer', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();

    $this->actingAs($first)->post(route('polls.store', $group), [
        'title' => 'Dinner night',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => '2027-10-01', 'starts_at' => null, 'ends_at' => null, 'label' => null]],
    ])->assertSessionHas('success', 'Poll created');

    expect(notifKinds($owner))->toBe(['new_poll'])
        ->and(notifKinds($second))->toBe(['new_poll'])
        ->and(notifKinds($first))->toBe([]);
});

it('F-23: a follow-up poll notifies only accepted participants and managers', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();
    $organizer = User::factory()->create();
    $group->addMember($organizer, GroupRole::Member, now());
    $event = notifEvent($group, $organizer, [[$organizer, ParticipationStatus::Accepted], [$first, ParticipationStatus::Accepted], [$second, ParticipationStatus::Pending]]);

    $this->actingAs($organizer)->post(route('events.polls.store', $event), [
        'title' => 'Rehearsal',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => '2027-05-03']],
    ])->assertSessionHasNoErrors();

    expect(notifKinds($first))->toBe(['new_poll'])
        ->and(notifKinds($owner))->toBe(['new_poll'])
        ->and(notifKinds($second))->toBe([])
        ->and(notifKinds($organizer))->toBe([]);
});

it('F-23: a new event notifies every member except its organizer', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();

    $this->actingAs($owner)->post(route('events.store', $group), ['title' => 'Gig', 'date' => '2027-03-12'])->assertSessionHas('success');

    $notification = $first->notifications()->firstOrFail();

    expect($notification->data['kind'])->toBe('new_event')
        ->and($notification->data['params'])->toBe(['name' => 'Olivia', 'title' => 'Gig', 'date' => '2027-03-12'])
        ->and(notifKinds($second))->toBe(['new_event'])
        ->and(notifKinds($owner))->toBe([]);
});

it('F-23: moving an event notifies the participants who did not decline, editing other details does not', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();
    $event = notifEvent($group, $owner, [[$owner, ParticipationStatus::Accepted], [$first, ParticipationStatus::Accepted], [$second, ParticipationStatus::Declined]]);
    $input = ['title' => 'Gig', 'date' => '2027-03-12', 'location' => 'Rex'];

    $this->actingAs($owner)->patch(route('events.update', $event), $input)->assertSessionHasNoErrors();

    expect(notifKinds($first))->toBe([]);

    $this->actingAs($owner)->patch(route('events.update', $event), [...$input, 'date' => '2027-03-19'])->assertSessionHasNoErrors();

    expect(notifKinds($first))->toBe(['moved'])
        ->and($first->notifications()->first()->data['params']['date'])->toBe('2027-03-19')
        ->and(notifKinds($second))->toBe([])
        ->and(notifKinds($owner))->toBe([]);
});

it('F-23: a comment in a list notifies people with a claim on it, except the author', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id, 'title' => 'Barbecue']);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $item->claims()->create(['user_id' => $first->id, 'quantity' => null]);

    $this->actingAs($owner)->post(route('rosters.comments.store', $roster), ['body' => 'Starts at six'])->assertSessionHas('success');
    $this->actingAs($first)->post(route('rosters.items.comments.store', [$roster, $item]), ['body' => 'Mine'])->assertSessionHas('success');

    expect(notifKinds($first))->toBe(['comment'])
        ->and($first->notifications()->first()->data['params'])->toBe(['name' => 'Olivia', 'list' => 'Barbecue'])
        ->and(notifKinds($second))->toBe([])
        ->and(notifKinds($owner))->toBe([]);
});

it('F-23: a claimant who may no longer see an event list hears nothing about its comments', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $event = notifEvent($group, $owner, [[$owner, ParticipationStatus::Accepted], [$first, ParticipationStatus::Declined]]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => null, 'event_id' => $event->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $item->claims()->create(['user_id' => $first->id, 'quantity' => null]);

    $this->actingAs($owner)->post(route('rosters.comments.store', $roster), ['body' => 'Hi'])->assertSessionHas('success');

    expect(notifKinds($first))->toBe([]);
});

it('F-23: a claim or release by another person on one\'s behalf notifies that person', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'PA system', 'quantity' => null]);

    $this->actingAs($owner)->post(route('rosters.items.claim.store', [$roster, $item]), ['user_id' => $first->id])->assertSessionHas('success');
    $this->actingAs($owner)->delete(route('rosters.items.claim.destroy', [$roster, $item]), ['user_id' => $first->id])->assertSessionHas('success');

    expect(notifKinds($first))->toEqualCanonicalizing(['claimed_for', 'released'])
        ->and($first->notifications()->first()->data['params'])->toBe(['name' => 'Olivia', 'item' => 'PA system']);
});

it('F-23: one\'s own claim and release notify nobody', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $this->actingAs($first)->post(route('rosters.items.claim.store', [$roster, $item]))->assertSessionHas('success');
    $this->actingAs($first)->delete(route('rosters.items.claim.destroy', [$roster, $item]))->assertSessionHas('success');

    expect(notifKinds($first))->toBe([])
        ->and(notifKinds($owner))->toBe([]);
});

it('F-23: notifications are delivered in the app and live, never by email', function () {
    ['group' => $group, 'owner' => $owner] = notifGroup();
    $event = notifEvent($group, $owner, []);

    expect((new DateFixed($event, $owner))->via($owner))->toBe(['database', 'broadcast']);
});

it('F-23: a new notification is pushed live on the recipient\'s private channel in their language', function () {
    EventFacade::fake([BroadcastNotificationCreated::class]);
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $first->update(['language' => 'de']);

    $this->actingAs($owner)->post(route('events.store', $group), ['title' => 'Gig', 'date' => '2027-03-12']);

    EventFacade::assertDispatched(BroadcastNotificationCreated::class, fn (BroadcastNotificationCreated $broadcast) => $broadcast->notifiable->is($first)
        && $broadcast->broadcastOn()[0]->name === 'private-App.Models.User.'.$first->id
        && $broadcast->broadcastType() === 'notification.created'
        && $broadcast->data['kind'] === 'new_event'
        && $broadcast->data['key'] === 'notif.newEvent'
        && $broadcast->data['text'] === 'Olivia hat das Event Gig angelegt');
});

it('F-23: the panel lists notifications in the viewer\'s language with key, params and a link', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $event = notifEvent($group, $owner, [[$first, ParticipationStatus::Accepted]]);
    $first->notify(new DateFixed($event, $owner));

    $this->actingAs($first)->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread', 1)
        ->assertJsonPath('per_page', 20)
        ->assertJsonPath('next_page', null)
        ->assertJsonPath('notifications.0.kind', 'fixed')
        ->assertJsonPath('notifications.0.key', 'notif.fixed')
        ->assertJsonPath('notifications.0.params.title', 'Gig')
        ->assertJsonPath('notifications.0.params.date', '2027-03-12')
        ->assertJsonPath('notifications.0.text', 'Date fixed: Gig on 12 Mar 2027')
        ->assertJsonPath('notifications.0.url', route('events.show', $event))
        ->assertJsonPath('notifications.0.read_at', null);

    $first->update(['language' => 'de']);

    $this->actingAs($first)->withSession(['locale' => 'de'])->getJson(route('notifications.index'))
        ->assertJsonPath('notifications.0.text', 'Termin festgelegt: Gig am 12.03.2027');
});

it('F-23: the panel pages through older notifications and drops the link of a deleted subject', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $event = notifEvent($group, $owner, []);

    foreach (range(1, 21) as $ignored) {
        $first->notify(new DateFixed($event, $owner));
    }

    $event->delete();

    $this->actingAs($first)->getJson(route('notifications.index'))
        ->assertJsonCount(20, 'notifications')
        ->assertJsonPath('next_page', 2)
        ->assertJsonPath('notifications.0.url', null);

    $this->actingAs($first)->getJson(route('notifications.index', ['page' => 2]))
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('next_page', null);
});

it('F-23: a person marks one notification as read, but never someone else\'s', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first, 'second' => $second] = notifGroup();
    $event = notifEvent($group, $owner, []);
    $first->notify(new DateFixed($event, $owner));
    $second->notify(new DateFixed($event, $owner));
    $notification = $first->notifications()->firstOrFail();

    $this->actingAs($second)->postJson(route('notifications.read', $notification->id))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();

    $this->actingAs($first)->postJson(route('notifications.read', $notification->id))->assertOk()->assertExactJson(['unread' => 0]);

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($second->unreadNotifications()->count())->toBe(1);
});

it('F-23: guests cannot reach the notification endpoints and see no unread count', function () {
    $this->getJson(route('notifications.index'))->assertUnauthorized();
    $this->postJson(route('notifications.read-all'))->assertUnauthorized();

    $this->get(route('welcome'))->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 0));
});

describe('channel authorization', function () {
    beforeEach(function () {
        // The "null" broadcaster authorizes every channel, so switch to the real driver and re-register the callbacks.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    });

    it('F-23: only the account itself listens on its notification channel', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $status = fn (User $actingUser) => $this->actingAs($actingUser)
            ->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.'.$user->id, 'socket_id' => '1234.1234'])
            ->status();

        expect($status($user))->toBe(200)
            ->and($status($other))->toBe(403);
    });
});

it('F-23: entries created in the same second are paged without skips or repeats', function () {
    ['group' => $group, 'owner' => $owner, 'first' => $first] = notifGroup();
    $event = notifEvent($group, $owner, []);

    foreach (range(1, 25) as $ignored) {
        $first->notify(new DateFixed($event, $owner));
    }

    $first->notifications()->update(['created_at' => now()->startOfSecond()]);

    $ids = collect([1, 2])->flatMap(
        fn (int $page) => collect($this->actingAs($first)->getJson(route('notifications.index', ['page' => $page]))->json('notifications'))->pluck('id'),
    );

    expect($ids)->toHaveCount(25)
        ->and($ids->unique())->toHaveCount(25)
        ->and($ids->all())->toBe($first->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id')->pluck('id')->all());
});

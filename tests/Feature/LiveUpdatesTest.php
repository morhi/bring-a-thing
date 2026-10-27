<?php

use App\Enums\GroupRole;
use App\Events\ClaimChanged;
use App\Events\DashboardChanged;
use App\Events\EventUpdated;
use App\Events\ItemChanged;
use App\Events\OptionFixed;
use App\Events\ParticipationChanged;
use App\Events\PollChanged;
use App\Events\PollResponseUpdated;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event as EventFacade;

/**
 * Create a group with an owner and one accepted and one declined plain member, plus an event with a list.
 *
 * @return array{group: Group, owner: User, accepted: User, declined: User, event: Event, roster: Roster}
 */
function liveEventSetup(): array
{
    $owner = User::factory()->create();
    $accepted = User::factory()->create();
    $declined = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($accepted, GroupRole::Member, now());
    $group->addMember($declined, GroupRole::Member, now());

    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $owner->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $accepted->id]);
    EventParticipation::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $declined->id]);
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => null, 'event_id' => $event->id]);

    return compact('group', 'owner', 'accepted', 'declined', 'event', 'roster');
}

/**
 * Ask the broadcaster to authorize the user on the given channel.
 */
function authorizeChannel(User $user, string $channelName): int
{
    return test()->actingAs($user)
        ->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.1234'])
        ->status();
}

describe('channel authorization', function () {
    beforeEach(function () {
        // The "null" broadcaster authorizes every channel, so switch to the real
        // driver and re-register the callbacks bound to the "null" one at boot.
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    });

    it('authorizes group members on the event channel and denies outsiders', function () {
        ['event' => $event, 'accepted' => $accepted, 'declined' => $declined] = liveEventSetup();

        expect(authorizeChannel($accepted, 'private-event.'.$event->id))->toBe(200)
            ->and(authorizeChannel($declined, 'private-event.'.$event->id))->toBe(200)
            ->and(authorizeChannel(User::factory()->create(), 'private-event.'.$event->id))->toBe(403);
    });

    it('denies non-participants the channels of an event list', function () {
        ['roster' => $roster, 'accepted' => $accepted, 'declined' => $declined] = liveEventSetup();

        expect(authorizeChannel($accepted, 'private-roster.'.$roster->id))->toBe(200)
            ->and(authorizeChannel($accepted, 'presence-presence.roster.'.$roster->id))->toBe(200)
            ->and(authorizeChannel($declined, 'private-roster.'.$roster->id))->toBe(403)
            ->and(authorizeChannel($declined, 'presence-presence.roster.'.$roster->id))->toBe(403);
    });

    it('authorizes group members on the poll private and presence channels and denies outsiders', function () {
        ['group' => $group, 'owner' => $owner, 'accepted' => $member] = liveEventSetup();
        $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
        $outsider = User::factory()->create();

        expect(authorizeChannel($member, 'private-poll.'.$poll->id))->toBe(200)
            ->and(authorizeChannel($member, 'presence-presence.poll.'.$poll->id))->toBe(200)
            ->and(authorizeChannel($outsider, 'private-poll.'.$poll->id))->toBe(403)
            ->and(authorizeChannel($outsider, 'presence-presence.poll.'.$poll->id))->toBe(403);
    });

    it('F-21: only the account itself may listen on its private channel for dashboard refreshes', function () {
        $user = User::factory()->create();

        expect(authorizeChannel($user, 'private-App.Models.User.'.$user->id))->toBe(200)
            ->and(authorizeChannel(User::factory()->create(), 'private-App.Models.User.'.$user->id))->toBe(403);
    });

    it('F-21: lists and polls show who is currently viewing them', function () {
        ['group' => $group, 'owner' => $owner, 'accepted' => $member, 'roster' => $roster] = liveEventSetup();
        $member->forceFill(['name' => 'Mia'])->save();
        $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

        foreach (['presence-presence.poll.'.$poll->id, 'presence-presence.roster.'.$roster->id] as $channelName) {
            $response = $this->actingAs($member)->postJson('/broadcasting/auth', ['channel_name' => $channelName, 'socket_id' => '1234.1234']);

            $response->assertOk();
            expect(json_decode($response->json('channel_data'), true))->toMatchArray(['user_id' => $member->id, 'user_info' => ['id' => $member->id, 'name' => 'Mia']]);
        }
    });

    it('denies follow-up poll channels to members without acceptance', function () {
        ['group' => $group, 'owner' => $owner, 'declined' => $declined, 'event' => $event] = liveEventSetup();
        $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'event_id' => $event->id]);

        expect(authorizeChannel($declined, 'private-poll.'.$poll->id))->toBe(403)
            ->and(authorizeChannel($declined, 'presence-presence.poll.'.$poll->id))->toBe(403);
    });
});

it('F-21: when a person claims an item, a second open session shows the claim within 2 seconds', function () {
    EventFacade::fake([ClaimChanged::class]);
    ['roster' => $roster, 'accepted' => $accepted] = liveEventSetup();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $this->actingAs($accepted)->post(route('rosters.items.claim.store', [$roster, $item]))->assertSessionHas('success', 'Claim saved');

    EventFacade::assertDispatched(ClaimChanged::class, fn (ClaimChanged $broadcast) => $broadcast->broadcastOn()->name === 'private-roster.'.$roster->id
        && $broadcast->broadcastAs() === 'claim.changed'
        && $broadcast->broadcastWith() === ['roster_id' => $roster->id, 'item_id' => $item->id, 'user_id' => $accepted->id]);
});

it('F-21: claims, items and poll responses appear in all open views without reloading', function () {
    EventFacade::fake([ClaimChanged::class, ItemChanged::class, PollResponseUpdated::class]);
    ['group' => $group, 'owner' => $owner, 'accepted' => $accepted, 'roster' => $roster] = liveEventSetup();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $accepted->id]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($accepted)->delete(route('rosters.items.claim.destroy', [$roster, $item]))->assertRedirect();
    $this->actingAs($owner)->post(route('rosters.items.store', $roster), ['name' => 'Cables'])->assertRedirect();
    $this->actingAs($owner)->delete(route('rosters.items.destroy', [$roster, $item]))->assertRedirect();
    $this->actingAs($accepted)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertRedirect();

    EventFacade::assertDispatched(ClaimChanged::class, fn (ClaimChanged $broadcast) => $broadcast->itemId === $item->id && $broadcast->userId === $accepted->id);
    EventFacade::assertDispatched(ItemChanged::class, fn (ItemChanged $broadcast) => $broadcast->broadcastAs() === 'item.changed' && ! $broadcast->deleted);
    EventFacade::assertDispatched(ItemChanged::class, fn (ItemChanged $broadcast) => $broadcast->itemId === $item->id && $broadcast->deleted);
    EventFacade::assertDispatched(PollResponseUpdated::class, fn (PollResponseUpdated $broadcast) => $broadcast->broadcastOn()->name === 'private-poll.'.$poll->id
        && $broadcast->broadcastWith() === ['poll_id' => $poll->id, 'option_id' => $option->id, 'user_id' => $accepted->id]);
});

it('F-21: acceptances appear in all open views without reloading', function () {
    EventFacade::fake([ParticipationChanged::class, ClaimChanged::class]);
    ['event' => $event, 'owner' => $owner, 'accepted' => $accepted, 'declined' => $declined, 'roster' => $roster] = liveEventSetup();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    RosterItemClaim::factory()->create(['roster_item_id' => $item->id, 'user_id' => $accepted->id]);

    $this->actingAs($accepted)->put(route('events.participation.update', $event), ['status' => 'declined', 'confirm_release' => true])->assertRedirect();
    $this->actingAs($owner)->put(route('events.participants.update', [$event, $declined]), ['status' => 'accepted'])->assertRedirect();

    EventFacade::assertDispatched(ParticipationChanged::class, fn (ParticipationChanged $broadcast) => $broadcast->broadcastOn()->name === 'private-event.'.$event->id && $broadcast->userId === $accepted->id);
    EventFacade::assertDispatched(ParticipationChanged::class, fn (ParticipationChanged $broadcast) => $broadcast->userId === $declined->id);
    EventFacade::assertDispatched(ClaimChanged::class, fn (ClaimChanged $broadcast) => $broadcast->rosterId === $roster->id && $broadcast->itemId === $item->id);
});

it('broadcasts event edits and deletion on the event channel', function () {
    EventFacade::fake([EventUpdated::class]);
    ['event' => $event, 'owner' => $owner] = liveEventSetup();

    $this->actingAs($owner)->patch(route('events.update', $event), [
        'title' => 'Moved gig',
        'date' => now()->addMonths(2)->format('Y-m-d'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($owner)->delete(route('events.destroy', $event))->assertRedirect();

    EventFacade::assertDispatched(EventUpdated::class, fn (EventUpdated $broadcast) => $broadcast->broadcastOn()->name === 'private-event.'.$event->id && ! $broadcast->deleted);
    EventFacade::assertDispatched(EventUpdated::class, fn (EventUpdated $broadcast) => $broadcast->eventId === $event->id && $broadcast->deleted);
});

it('broadcasts fixed options on the poll channel and poll changes such as closing', function () {
    EventFacade::fake([OptionFixed::class, PollChanged::class]);
    ['group' => $group, 'owner' => $owner] = liveEventSetup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success', 'Date fixed');
    $this->actingAs($owner)->delete(route('polls.reopen', $poll))->assertRedirect();

    $fixedEventId = Event::query()->where('origin_poll_option_id', $option->id)->value('id');
    EventFacade::assertDispatched(OptionFixed::class, fn (OptionFixed $broadcast) => $broadcast->broadcastOn()->name === 'private-poll.'.$poll->id
        && $broadcast->broadcastWith() === ['poll_id' => $poll->id, 'option_ids' => [$option->id], 'event_ids' => [$fixedEventId]]);
    EventFacade::assertDispatched(PollChanged::class, fn (PollChanged $broadcast) => $broadcast->pollId === $poll->id && ! $broadcast->deleted);
});

it('F-21: the dashboards of the group members refresh after new events, fixed dates, poll and participation changes', function () {
    EventFacade::fake([DashboardChanged::class]);
    ['group' => $group, 'owner' => $owner, 'accepted' => $accepted, 'declined' => $declined, 'event' => $event] = liveEventSetup();
    $outsider = User::factory()->create();
    $members = collect([$owner, $accepted, $declined])->map(fn (User $user) => 'private-App.Models.User.'.$user->id)->sort()->values()->all();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($owner)->post(route('events.store', $group), [
        'title' => 'Rehearsal',
        'date' => now()->addMonth()->format('Y-m-d'),
    ])->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');
    $this->actingAs($accepted)->put(route('events.participation.update', $event), ['status' => 'declined', 'confirm_release' => true])->assertRedirect();

    EventFacade::assertDispatchedTimes(DashboardChanged::class, 3);
    EventFacade::assertDispatched(DashboardChanged::class, function (DashboardChanged $broadcast) use ($group, $members, $outsider) {
        $channels = collect($broadcast->broadcastOn())->map(fn ($channel) => $channel->name)->sort()->values()->all();

        return $broadcast instanceof ShouldDispatchAfterCommit
            && $broadcast->broadcastAs() === 'dashboard.changed'
            && $broadcast->broadcastWith() === ['group_id' => $group->id]
            && $channels === $members
            && ! in_array('private-App.Models.User.'.$outsider->id, $channels, true);
    });
});

it('F-21: a poll answer refreshes only the dashboard of the person who answered', function () {
    EventFacade::fake([DashboardChanged::class]);
    ['group' => $group, 'owner' => $owner, 'accepted' => $member] = liveEventSetup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);

    $this->actingAs($member)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertRedirect();

    EventFacade::assertDispatched(DashboardChanged::class, fn (DashboardChanged $broadcast) => $broadcast->userIds === [$member->id]);
});

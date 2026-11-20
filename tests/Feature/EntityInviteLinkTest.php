<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Http\Responses\PasskeyLoginResponse;
use App\Mail\MagicLinkMail;
use App\Models\AccessGrant;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a group with an owner and a plain member.
 *
 * @return array{0: Group, 1: User, 2: User}
 */
function linkGroup(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $member];
}

it('F-26: a manager creates, regenerates and revokes the event invite link with feedback', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($owner)->post(route('events.invite-link.store', $event))->assertRedirect()->assertSessionHas('success', 'Invite link created');
    $token = $event->fresh()->invite_token;
    expect($token)->not->toBeNull();

    $this->actingAs($owner)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.invite_link.url', route('events.join.show', $token))
            ->where('page.permissions.can_manage_invite_link', true)
            ->missing('page.event.invite_token'));

    $this->actingAs($owner)->post(route('events.invite-link.regenerate', $event))->assertSessionHas('success', 'New invite link created, the old one shows "Not found"');
    expect($event->fresh()->invite_token)->not->toBe($token)->not->toBeNull();

    $this->actingAs($owner)->delete(route('events.invite-link.destroy', $event))->assertSessionHas('success', 'Invite link revoked, the old link shows "Not found"');
    expect($event->fresh()->invite_token)->toBeNull();
});

it('F-27: a manager creates, regenerates and revokes the poll invite link with feedback', function () {
    [$group, $owner] = linkGroup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->actingAs($owner)->post(route('polls.invite-link.store', $poll))->assertSessionHas('success', 'Invite link created');
    $token = $poll->fresh()->invite_token;

    $this->actingAs($owner)
        ->get(route('polls.show', $poll))
        ->assertInertia(fn (Assert $page) => $page->where('page.invite_link.url', route('polls.join.show', $token)));

    $this->actingAs($owner)->post(route('polls.invite-link.regenerate', $poll))->assertSessionHas('success');
    expect($poll->fresh()->invite_token)->not->toBe($token);

    $this->actingAs($owner)->delete(route('polls.invite-link.destroy', $poll))->assertSessionHas('success');
    expect($poll->fresh()->invite_token)->toBeNull();
});

it('F-26: members without a manager role and guests may not manage invite links', function () {
    [$group, $owner, $member] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $guest = User::factory()->create();
    grantOn($event, $guest);
    grantOn($poll, $guest);

    foreach ([$member, $guest] as $user) {
        $this->actingAs($user)->post(route('events.invite-link.store', $event))->assertForbidden();
        $this->actingAs($user)->post(route('events.invite-link.regenerate', $event))->assertForbidden();
        $this->actingAs($user)->delete(route('events.invite-link.destroy', $event))->assertForbidden();
        $this->actingAs($user)->post(route('polls.invite-link.store', $poll))->assertForbidden();
    }
});

it('F-26 F-27: the Share dialog data reaches only managers of a group event or poll', function (string $role, bool $sees) {
    [$group, $owner, $member] = linkGroup();
    $admin = User::factory()->create();
    $group->addMember($admin, GroupRole::Admin, now());
    $organizer = User::factory()->create();
    $group->addMember($organizer, GroupRole::Member, now());
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);
    $eventToken = $event->enableInviteLink();
    $pollToken = $poll->enableInviteLink();
    $guest = User::factory()->create();
    grantOn($event, $guest);
    grantOn($poll, $guest);
    $user = ['organizer' => $organizer, 'owner' => $owner, 'admin' => $admin, 'member' => $member, 'guest' => $guest][$role];

    $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $sees
        ? $page->where('page.invite_link.url', route('events.join.show', $eventToken))->where('page.permissions.can_manage_invite_link', true)
        : $page->where('page.invite_link', null)->where('page.permissions.can_manage_invite_link', false));
    $this->actingAs($user)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $sees
        ? $page->where('page.invite_link.url', route('polls.join.show', $pollToken))->where('page.permissions.can_manage_invite_link', true)
        : $page->where('page.invite_link', null)->where('page.permissions.can_manage_invite_link', false));
})->with([['organizer', true], ['owner', true], ['admin', true], ['member', false], ['guest', false]]);

it('F-26 F-27: a standalone event or poll gives the Share dialog data to its organizer only', function (bool $isOrganizer) {
    $organizer = User::factory()->create();
    $event = Event::factory()->create(['group_id' => null, 'organizer_id' => $organizer->id]);
    $poll = Poll::factory()->create(['group_id' => null, 'organizer_id' => $organizer->id]);
    $event->enableInviteLink();
    $poll->enableInviteLink();
    $user = $isOrganizer ? $organizer : User::factory()->create();

    if (! $isOrganizer) {
        grantOn($event, $user);
        grantOn($poll, $user);
    }

    foreach (['events.show' => $event, 'polls.show' => $poll] as $route => $entity) {
        $this->actingAs($user)->get(route($route, $entity))->assertInertia(fn (Assert $page) => $isOrganizer
            ? $page->where('page.invite_link.url', fn ($url) => $url !== null)
            : $page->where('page.invite_link', null));
    }
})->with([[true], [false]]);

it('F-26: a guest joins an event with a name only and stays logged in', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $token = $event->enableInviteLink();

    $this->get(route('events.join.show', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Events/Join', false)
            ->where('join.kind', 'event')
            ->where('join.title', $event->title)
            ->where('join.is_authenticated', false)
            ->missing('join.group_name'));

    $this->post(route('events.join.store', $token), ['name' => 'Name Only'])
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('success', 'You have access now');

    $guest = User::query()->where('name', 'Name Only')->firstOrFail();
    expect($guest->email)->toBeNull();
    $this->assertAuthenticatedAs($guest);
    expect($event->grantsAccessTo($guest))->toBeTrue();
    expect(grantSet($guest))->toBe(grantKey($event));
    expect($group->hasMember($guest))->toBeFalse();
    $this->get(route('events.show', $event))->assertOk();
});

it('F-26: a guest without name and email gets a validation error', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);

    $this->post(route('events.join.store', $event->enableInviteLink()), [])->assertInvalid('name');

    $this->assertGuest();
    expect(AccessGrant::query()->count())->toBe(0);
});

it('F-26: a guest who joins with an email gets access only after the login link', function () {
    Mail::fake();
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $token = $event->enableInviteLink();

    $this->post(route('events.join.store', $token), ['name' => 'Mail Joiner', 'email' => 'mail@example.com'])
        ->assertSessionHas('success', 'A login link has been sent to your email address.');

    $joiner = User::query()->where('email', 'mail@example.com')->firstOrFail();
    $this->assertGuest();
    expect($event->grantsAccessTo($joiner))->toBeFalse();
    expect(AccessGrant::query()->count())->toBe(0);

    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return $mail->hasTo('mail@example.com');
    });

    $this->get($url)->assertRedirect(route('events.show', $event));

    $this->assertAuthenticatedAs($joiner);
    expect($event->grantsAccessTo($joiner))->toBeTrue();
});

it('F-26/F-27: an account with a password or passkey logs in on the join page and lands on the item with access', function (string $kind) {
    [$group, $owner] = linkGroup();
    $entity = $kind === 'event'
        ? Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id])
        : Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $token = $entity->enableInviteLink();
    $showRoute = $kind === 'event' ? 'events.show' : 'polls.show';
    $withPassword = User::factory()->create();
    $withPasskey = User::factory()->create();

    $this->post(route('login.password'), ['email' => $withPassword->email, 'password' => 'password', "join_{$kind}" => $token])
        ->assertRedirect(route($showRoute, $entity))
        ->assertSessionHas('success', 'You have access now');

    $request = Request::create(route('passkey.login'), 'POST', ["join_{$kind}" => $token]);
    $request->setUserResolver(fn () => $withPasskey);
    $request->setLaravelSession(app('session.store'));
    $response = app(PasskeyLoginResponse::class)->toResponse($request);

    expect($response->getData(true))->toBe(['redirect' => route($showRoute, $entity)]);
    expect(grantSet($withPassword))->toBe(grantKey($entity));
    expect(grantSet($withPasskey))->toBe(grantKey($entity));
})->with(['event', 'poll']);

it('F-26: a password login with a revoked event invite token lands on the dashboard', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $revokedToken = $event->enableInviteLink();
    $event->forceFill(['invite_token' => null])->save();
    $user = User::factory()->create();

    $this->post(route('login.password'), ['email' => $user->email, 'password' => 'password', 'join_event' => $revokedToken])
        ->assertRedirect(route('dashboard'));

    expect($event->grantsAccessTo($user))->toBeFalse();
    expect(AccessGrant::query()->count())->toBe(0);
});

it('F-27: a logged-in account joins a poll with one click and may answer it but not edit it', function () {
    [$group, $owner] = linkGroup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $token = $poll->enableInviteLink();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('polls.show', $poll))->assertNotFound();
    $this->actingAs($outsider)->get(route('polls.join.show', $token))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Polls/Join', false)->where('join.is_authenticated', true));

    $this->actingAs($outsider)->post(route('polls.join.store', $token))->assertRedirect(route('polls.show', $poll))->assertSessionHas('success');

    $this->actingAs($outsider)
        ->get(route('polls.show', $poll))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_respond', true)
            ->where('page.permissions.can_edit', false)
            ->where('page.permissions.can_view_group', true)
            ->where('page.audience_count', 3));
    $this->actingAs($outsider)->post(route('polls.responses.store', [$poll, $option]), ['status' => 'yes'])->assertRedirect();
    expect($option->responses()->where('user_id', $outsider->id)->exists())->toBeTrue();
    $this->actingAs($outsider)->get(route('polls.edit', $poll))->assertForbidden();
    $this->actingAs($outsider)->get(route('polls.join.show', $token))->assertRedirect(route('polls.show', $poll));
});

it('F-26: a guest with an event grant may respond and comment but not edit the event', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $guest = User::factory()->create();
    grantOn($event, $guest);

    $this->actingAs($guest)->put(route('events.participation.update', $event), ['status' => 'accepted'])->assertRedirect()->assertSessionHas('success');
    expect($event->fresh()->statusOf($guest))->toBe(ParticipationStatus::Accepted);

    $this->actingAs($guest)->post(route('events.comments.store', $event), ['body' => 'See you there'])->assertRedirect();
    expect($event->comments()->where('user_id', $guest->id)->exists())->toBeTrue();

    $this->actingAs($guest)->get(route('events.edit', $event))->assertForbidden();
    $this->actingAs($guest)->delete(route('events.destroy', $event))->assertForbidden();
    $this->actingAs($guest)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.permissions.can_edit', false)
            ->where('page.permissions.can_view_group', true)
            ->where('page.permissions.can_create_sub_event', false));
});

it('F-26: a manager sets the status of a guest, who shows among the participants', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $guest = User::factory()->create(['name' => 'Guest Person']);
    grantOn($event, $guest);

    $this->actingAs($owner)
        ->get(route('events.show', $event))
        ->assertInertia(fn (Assert $page) => $page->has('page.participants', 3)->where('page.participants.2.name', 'Guest Person'));

    $this->actingAs($owner)->put(route('events.participants.update', [$event, $guest]), ['status' => 'pending'])->assertSessionHasNoErrors();
    expect($event->fresh()->statusOf($guest))->toBe(ParticipationStatus::Pending);
});

it('F-26: an event grant covers child events, accepted follow-up polls and lists, but no event outside the granted ancestor chain or the group', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $child = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'parent_event_id' => $event->id]);
    $followUp = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'event_id' => $event->id]);
    $list = Roster::factory()->create(['owner_id' => $owner->id, 'event_id' => $event->id]);
    $sibling = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $groupPoll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $guest = User::factory()->create();
    grantOn($event, $guest);

    $this->actingAs($guest)->get(route('events.show', $child))->assertOk();
    $this->actingAs($guest)->get(route('polls.show', $followUp))->assertNotFound();
    $this->actingAs($guest)->get(route('rosters.show', $list))->assertNotFound();

    $this->actingAs($guest)->put(route('events.participation.update', $event), ['status' => 'accepted']);

    $this->actingAs($guest)->get(route('polls.show', $followUp))->assertOk();
    $this->actingAs($guest)->get(route('rosters.show', $list))->assertOk();
    $this->actingAs($guest)->get(route('events.show', $sibling))->assertNotFound();
    $this->actingAs($guest)->get(route('polls.show', $groupPoll))->assertNotFound();
    expect($followUp->fresh()->audience()->pluck('id'))->toContain($guest->id);
});

it('F-27: a poll grant covers the events fixed from it, but not other events', function () {
    [$group, $owner] = linkGroup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $option = PollOption::factory()->create(['poll_id' => $poll->id]);
    $fixed = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'origin_poll_option_id' => $option->id]);
    $other = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $guest = User::factory()->create();
    grantOn($poll, $guest);

    $this->actingAs($guest)->get(route('events.show', $fixed))->assertOk();
    $this->actingAs($guest)->put(route('events.participation.update', $fixed), ['status' => 'accepted'])->assertRedirect();
    $this->actingAs($guest)->get(route('events.show', $other))->assertNotFound();
});

it('F-26: a revoked or replaced event link shows "Not found"', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $oldToken = $event->enableInviteLink();
    $event->regenerateInviteToken();

    $this->get(route('events.join.show', $oldToken))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Events/Join', false)->where('join', null));
    $this->post(route('events.join.store', $oldToken), ['name' => 'Too Late'])->assertNotFound();

    $this->actingAs($owner)->delete(route('events.invite-link.destroy', $event));
    Auth::logout();
    $this->get(route('events.join.show', 'unknown-token'))->assertNotFound();
});

it('F-27: a revoked poll link shows "Not found"', function () {
    [$group, $owner] = linkGroup();
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $token = $poll->enableInviteLink();
    $poll->revokeInviteLink();

    $this->get(route('polls.join.show', $token))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Polls/Join', false)->where('join', null));
    $this->post(route('polls.join.store', $token), ['name' => 'Too Late'])->assertNotFound();
    expect(User::query()->where('name', 'Too Late')->exists())->toBeFalse();
});

it('F-26: events and polls reached through a grant appear on the dashboard and in search', function () {
    [$group, $owner] = linkGroup();
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Picnic in the park']);
    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Picnic date']);
    PollOption::factory()->create(['poll_id' => $poll->id]);
    Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, 'title' => 'Picnic sibling']);
    $guest = User::factory()->create();
    grantOn($event, $guest);
    grantOn($poll, $guest);
    $event->participations()->create(['user_id' => $guest->id, 'status' => ParticipationStatus::Pending]);

    $this->actingAs($guest)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('waiting', 2)
        ->where('waiting.0.slug', $poll->slug)
        ->where('waiting.1.slug', $event->slug)
        ->has('groups', 1)
        ->where('groups.0.slug', $group->slug)
        ->where('groups.0.is_guest', true));

    $response = $this->actingAs($guest)->getJson(route('search', ['q' => 'picnic']))->assertOk();
    $titles = collect($response->json('sections'))->mapWithKeys(fn (array $section) => [$section['type'] => collect($section['results'])->pluck('title')->all()])->all();

    expect($titles)->toBe([
        'polls' => ['Picnic date'],
        'events' => ['Picnic in the park'],
    ]);
});

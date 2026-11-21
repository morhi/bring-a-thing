<?php

use App\Http\Responses\PasskeyLoginResponse;
use App\Models\AccessGrant;
use App\Models\Event;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Join through the invite link of the item as the given account.
 */
function joinAs(User $user, Event|Poll $item): void
{
    $route = $item instanceof Event ? 'events' : 'polls';
    test()->actingAs($user)->post(route("{$route}.join.store", $item->enableInviteLink()))->assertRedirect(route("{$route}.show", $item));
}

it('F-26/F-27: joining through any invite link grants the topmost ancestor and the shared item', function (string $shared, array $expected) {
    $c = linkChain();
    $item = $c[$shared];
    $token = $item->enableInviteLink();
    $route = $item instanceof Event ? 'events' : 'polls';

    $this->post(route("{$route}.join.store", $token), ['name' => 'Link Guest'])
        ->assertRedirect(route("{$route}.show", $item))
        ->assertSessionHas('success', 'You have access now');

    $guest = User::query()->where('name', 'Link Guest')->firstOrFail();
    expect(grantSet($guest))->toBe(grantKey(...array_map(fn ($key) => $c[$key], $expected)));
    $this->get(route("{$route}.show", $item))->assertOk();
})->with([
    'top-level event' => ['other', ['other']],
    'top-level poll' => ['poll', ['poll']],
    'event fixed from poll' => ['fixed', ['poll', 'fixed']],
    'child of fixed event' => ['child', ['poll', 'child']],
    'follow-up poll of fixed event' => ['followUp', ['poll', 'followUp']],
]);

it('F-26: a plain follow-up poll and a three-hop event resolve their topmost ancestor', function () {
    $c = linkChain();
    $top = $c['other'];
    $plainFollowUp = Poll::factory()->create(['group_id' => $c['group']->id, 'organizer_id' => $top->organizer_id, 'event_id' => $top->id]);
    $threeHops = Event::factory()->create([
        'group_id' => $c['group']->id,
        'organizer_id' => $top->organizer_id,
        'parent_event_id' => $c['fixed']->id,
        'origin_poll_option_id' => PollOption::factory()->create(['poll_id' => $c['followUp']->id])->id,
    ]);

    foreach ([[$plainFollowUp, [$top, $plainFollowUp]], [$threeHops, [$c['poll'], $threeHops]]] as [$item, $expected]) {
        $guest = User::factory()->create();
        joinAs($guest, $item);
        expect(grantSet($guest))->toBe(grantKey(...$expected));
    }
});

it('F-26: every join path grants the same set and joining again creates no duplicate', function (string $path) {
    $c = linkChain();
    $token = $c['child']->enableInviteLink();
    $guest = User::factory()->create(['password' => 'secret-password']);
    $expected = grantKey($c['poll'], $c['child']);

    match ($path) {
        'logged in' => $this->actingAs($guest)->post(route('events.join.store', $token))->assertRedirect(route('events.show', $c['child'])),
        'password' => $this->post(route('login.password'), ['email' => $guest->email, 'password' => 'secret-password', 'join_event' => $token])->assertRedirect(route('events.show', $c['child'])),
        'magic link' => (function () use ($guest, $token, $c) {
            $this->post(route('events.join.store', $token), ['name' => 'Mail', 'email' => $guest->email]);
            expect(AccessGrant::query()->count())->toBe(0);
            $this->get(URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id, 'join_event' => $token]))
                ->assertRedirect(route('events.show', $c['child']));
        })(),
        'passkey' => (function () use ($guest, $token, $c) {
            $request = Request::create(route('passkey.login'), 'POST', ['join_event' => $token]);
            $request->setUserResolver(fn () => $guest);
            $request->setLaravelSession(app('session.store'));
            expect(app(PasskeyLoginResponse::class)->toResponse($request)->getData(true))->toBe(['redirect' => route('events.show', $c['child'])]);
        })(),
    };

    expect(grantSet($guest))->toBe($expected);

    $this->actingAs($guest)->post(route('events.join.store', $token))->assertRedirect(route('events.show', $c['child']));
    expect(grantSet($guest))->toBe($expected);
})->with(['logged in', 'password', 'magic link', 'passkey']);

it('F-26: a user already admitted through the parent grant still gets the direct grant on the child', function () {
    $c = linkChain();
    $guest = User::factory()->create();
    grantOn($c['fixed'], $guest);
    $token = $c['child']->enableInviteLink();

    $this->actingAs($guest)->get(route('events.join.show', $token))->assertRedirect(route('events.show', $c['child']));
    $this->post(route('events.join.store', $token))->assertRedirect(route('events.show', $c['child']));

    expect(grantSet($guest))->toBe(grantKey($c['poll'], $c['fixed'], $c['child']));
});

it('F-27: a poll link password login for a follow-up poll lands on the poll with the chain granted', function () {
    $c = linkChain();
    $guest = User::factory()->create(['password' => 'secret-password']);

    $this->post(route('login.password'), ['email' => $guest->email, 'password' => 'secret-password', 'join_poll' => $c['followUp']->enableInviteLink()])
        ->assertRedirect(route('polls.show', $c['followUp']))
        ->assertSessionHas('success', 'You have access now');

    expect(grantSet($guest))->toBe(grantKey($c['poll'], $c['followUp']));
    $this->get(route('events.show', $c['fixed']))->assertOk();
    $this->get(route('polls.show', $c['poll']))->assertOk();
    $this->get(route('events.show', $c['other']))->assertNotFound();
});

it('F-12: a follow-up poll link guest answers at once, but opens another follow-up poll only after accepting', function () {
    $c = linkChain();
    $second = Poll::factory()->create(['group_id' => $c['group']->id, 'organizer_id' => $c['fixed']->organizer_id, 'event_id' => $c['fixed']->id]);
    $option = PollOption::factory()->create(['poll_id' => $c['followUp']->id]);
    $guest = User::factory()->create();
    joinAs($guest, $c['followUp']);

    $this->post(route('polls.responses.store', [$c['followUp'], $option]), ['status' => 'yes'])->assertRedirect();

    expect($option->responses()->where('user_id', $guest->id)->where('status', 'yes')->exists())->toBeTrue();
    expect($c['followUp']->fresh()->audience()->pluck('id'))->toContain($guest->id);
    $this->get(route('polls.show', $c['followUp']))->assertInertia(fn (Assert $page) => $page->where('page.permissions.can_respond', true));

    $this->get(route('polls.show', $second))->assertNotFound();
    $this->put(route('events.participation.update', $c['fixed']), ['status' => 'accepted']);
    $this->get(route('polls.show', $second))->assertOk();
});

it('F-12: the audience note flags a follow-up poll viewer who has not accepted the source event', function () {
    $c = linkChain();
    $guest = User::factory()->create();
    joinAs($guest, $c['followUp']);

    $this->get(route('polls.show', $c['followUp']))->assertInertia(fn (Assert $page) => $page->where('page.answers_without_acceptance', true));

    $this->put(route('events.participation.update', $c['fixed']), ['status' => 'accepted']);
    $this->get(route('polls.show', $c['followUp']))->assertInertia(fn (Assert $page) => $page->where('page.answers_without_acceptance', false));
});

it('F-26: a child event link guest opens the parent event and its poll, shows among its guests, and nothing else', function () {
    $c = linkChain();
    $guest = User::factory()->create(['name' => 'Chain Guest']);
    joinAs($guest, $c['child']);

    $this->get(route('events.show', $c['child']))->assertOk();
    $this->get(route('events.show', $c['fixed']))->assertOk();
    $this->get(route('polls.show', $c['poll']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_respond', true)
        ->where('page.permissions.can_edit', false));
    $this->get(route('events.show', $c['other']))->assertNotFound();
    $this->get(route('events.edit', $c['fixed']))->assertForbidden();

    $organizer = User::query()->findOrFail($c['fixed']->organizer_id);
    $this->actingAs($organizer)->get(route('events.show', $c['fixed']))
        ->assertInertia(fn (Assert $page) => $page->where('page.participants', fn ($participants) => collect($participants)->pluck('name')->contains('Chain Guest')));
    $this->put(route('events.participants.update', [$c['fixed'], $guest]), ['status' => 'pending'])->assertSessionHasNoErrors();
});

it('F-26: search, dashboard and group page agree with the items a chain guest may open', function (string $shared) {
    $c = linkChain();
    $guest = User::factory()->create();
    joinAs($guest, $c[$shared]);
    $openable = fn (array $keys) => collect($keys)->filter(fn ($key) => $this->get(route($c[$key] instanceof Event ? 'events.show' : 'polls.show', $c[$key]))->status() === 200)->map(fn ($key) => $c[$key]->title)->sort()->values()->all();
    $keys = ['poll', 'fixed', 'child', 'other'];

    $found = collect($this->getJson(route('search', ['q' => 'Chain']))->json('sections'))
        ->flatMap(fn ($section) => in_array($section['type'], ['events', 'polls'], true) ? array_column($section['results'], 'title') : [])
        ->sort()->values()->all();

    expect($found)->toBe($openable($keys));
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('groups.0.is_guest', true));

    $response = $this->get(route('groups.show', $c['group']))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('page.is_guest', true)
        ->has('page.members', 0)
        ->where('page.invite_link', null)
        ->where('page.permissions.can_create', false));
    expect(json_encode($response->inertiaProps()))->not->toContain('Chain other');
    expect(collect($response->inertiaProps('page.events'))->pluck('title')->sort()->values()->all())->toBe($openable(['fixed', 'child', 'other']));
    expect(collect($response->inertiaProps('page.polls'))->pluck('title')->all())->toBe(['Chain poll']);
    expect($c['group']->hasMember($guest))->toBeFalse();
})->with(['child event link' => ['child'], 'event fixed from poll link' => ['fixed']]);

it('F-26/F-29: a link guest of a standalone chain stays out of Without groups', function (string $shared) {
    $c = linkChain(User::factory()->create(), standalone: true);
    $guest = User::factory()->create();
    joinAs($guest, $c[$shared]);

    expect(grantSet($guest))->toBe(grantKey($c['poll'], $c[$shared]));
    $this->get(route('events.show', $c['fixed']))->assertOk();
    $this->get(route('events.show', $c['other']))->assertNotFound();
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('groups', 0)->where('withoutGroup', null));
    $this->get(route('without-group.show'))->assertInertia(fn (Assert $page) => $page->has('lists', 0)->has('events', 0)->has('polls', 0));
})->with(['child event' => ['child'], 'follow-up poll' => ['followUp']]);

it('F-26: a revoked invite token grants nothing, not even the ancestors', function () {
    $c = linkChain();
    $token = $c['child']->enableInviteLink();
    $c['child']->forceFill(['invite_token' => null])->save();

    $this->actingAs(User::factory()->create())->post(route('events.join.store', $token))->assertNotFound();

    expect(AccessGrant::query()->count())->toBe(0);
});

it('F-04: a group invite link creates a membership and no access grant', function () {
    $c = linkChain();
    $joiner = User::factory()->create();
    $token = $c['group']->enableInviteLink();

    $this->actingAs($joiner)->post(route('groups.join.store', $token))->assertRedirect();

    expect($c['group']->hasMember($joiner))->toBeTrue();
    expect(AccessGrant::query()->count())->toBe(0);
});

it('F-19: a login through the share link keeps the list open, grants the chain and finishes every action', function (string $action, string $login, string $on, array $expected) {
    $c = linkChain();
    $roster = Roster::factory()->create(['owner_id' => $c['fixed']->organizer_id, 'group_id' => $c['group']->id, 'event_id' => $c[$on]->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $second = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $guest = User::factory()->create(['password' => 'secret-password']);
    $from = route('shared-rosters.show', $roster->share_token);

    if ($action === 'add') {
        $this->from($from)->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Salad']);
    } else {
        stashListClaim($roster, $item);
    }

    match ($login) {
        'magic' => $this->get(URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]))->assertRedirect($from),
        'password' => $this->post(route('login.password'), ['email' => $guest->email, 'password' => 'secret-password', 'pending_claim_roster_token' => $roster->share_token])->assertRedirect($from),
        'passkey' => (function () use ($guest, $roster, $from) {
            $request = Request::create(route('passkey.login'), 'POST', ['pending_claim_roster_token' => $roster->share_token]);
            $request->setUserResolver(fn () => $guest);
            $request->setLaravelSession(app('session.store'));
            expect(app(PasskeyLoginResponse::class)->toResponse($request)->getData(true))->toBe(['redirect' => $from]);
            $this->actingAs($guest);
        })(),
    };

    expect(grantSet($guest))->toBe(grantKey(...array_map(fn ($key) => $c[$key], $expected)));

    if ($action === 'add') {
        $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Salad']);
    } else {
        $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $guest->id]);
    }

    $this->get($from)->assertOk();
    $this->post(route('shared-rosters.items.claim.store', [$roster->share_token, $second]))->assertSessionHas('success', 'Claim saved');
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $second->id, 'user_id' => $guest->id]);
    $this->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Chips'])->assertSessionHas('success');
    $this->get(route('events.show', $c[$on]))->assertOk();
    $this->get(route('events.show', $on === 'other' ? $c['fixed'] : $c['other']))->assertNotFound();
})->with([
    'claim, magic link, event fixed from poll' => ['claim', 'magic', 'fixed', ['poll', 'fixed']],
    'claim, password, child event' => ['claim', 'password', 'child', ['poll', 'child']],
    'add, magic link, top-level event' => ['add', 'magic', 'other', ['other']],
    'claim, passkey, child event' => ['claim', 'passkey', 'child', ['poll', 'child']],
]);

it('F-19: a logged-in claim or add through the share link grants the chain and keeps the list open', function (string $action) {
    $c = linkChain();
    $roster = Roster::factory()->create(['owner_id' => $c['fixed']->organizer_id, 'group_id' => $c['group']->id, 'event_id' => $c['child']->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $guest = User::factory()->create();
    $this->actingAs($guest);

    if ($action === 'add') {
        $this->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Salad'])->assertSessionHas('success');
        $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Salad']);
    } else {
        $this->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]))->assertSessionHas('success', 'Claim saved');
    }

    expect(grantSet($guest))->toBe(grantKey($c['poll'], $c['child']));
    $this->get(route('shared-rosters.show', $roster->share_token))->assertOk();
    $this->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Chips'])->assertSessionHas('success');
    $this->get(route('events.show', $c['child']))->assertOk();
})->with(['claim', 'add']);

it('F-19: a list without an event, viewing, or a vanished claim grants nothing', function (string $case) {
    $c = linkChain();
    $roster = Roster::factory()->create(['owner_id' => $c['fixed']->organizer_id, 'group_id' => $case === 'group list' ? $c['group']->id : null, 'event_id' => $case === 'regenerated' ? $c['fixed']->id : null]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $guest = User::factory()->create();

    if ($case === 'viewing') {
        $roster->update(['event_id' => $c['fixed']->id]);
        $this->get(route('shared-rosters.show', $roster->share_token))->assertOk();
        expect(AccessGrant::query()->count())->toBe(0);

        return;
    }

    stashListClaim($roster, $item);

    if ($case === 'regenerated') {
        $roster->regenerateShareToken();
    }

    $this->get(URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]));

    expect(AccessGrant::query()->count())->toBe(0);
})->with(['standalone list', 'group list', 'viewing', 'regenerated']);

it('F-19: the grant is created when the stashed claim fails', function () {
    $c = linkChain();
    $roster = Roster::factory()->create(['owner_id' => $c['fixed']->organizer_id, 'group_id' => $c['group']->id, 'event_id' => $c['child']->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $guest = User::factory()->create();

    stashListClaim($roster, $item);
    $item->delete();
    $this->get(URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]))->assertSessionHas('error');

    expect(grantSet($guest))->toBe(grantKey($c['poll'], $c['child']));
});

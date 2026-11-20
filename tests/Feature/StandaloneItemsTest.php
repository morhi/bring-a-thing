<?php

use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Events\DashboardChanged;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/**
 * Create a standalone poll or event organized by the user, with the organizer's own answer or acceptance.
 */
function standaloneItem(string $kind, User $organizer): Poll|Event
{
    if ($kind === 'poll') {
        $poll = dashboardPoll(null, $organizer, 2);
        $poll->options->first()?->responses()->create(['user_id' => $organizer->id, 'status' => PollResponseStatus::Yes]);

        return $poll;
    }

    return dashboardEvent(null, $organizer, $organizer, ParticipationStatus::Accepted);
}

// Visibility is the same rule for polls (F-27) and events (F-26), so each test runs per kind.
foreach (['poll' => 'F-27', 'event' => 'F-26'] as $kind => $feature) {
    it("{$feature}: a standalone {$kind} is hidden from others and opens after a grant", function () use ($kind) {
        $organizer = User::factory()->create();
        [$group, $owner, $member] = dashboardGroup();
        $item = standaloneItem($kind, $organizer);

        $this->actingAs($organizer)->get(route("{$kind}s.show", $item))->assertOk();
        $this->actingAs($member)->get(route("{$kind}s.show", $item))->assertNotFound();
        $this->actingAs($owner)->get(route("{$kind}s.show", $item))->assertNotFound();

        grantOn($item, $member);

        $this->actingAs($member)->get(route("{$kind}s.show", $item))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.permissions.can_edit', false)
                ->where('page.permissions.can_view_group', false));
        $this->actingAs($member)->get(route("{$kind}s.edit", $item))->assertForbidden();
        $this->actingAs($organizer)->get(route("{$kind}s.edit", $item))->assertOk();
    });

    it("F-29: the page \"Without groups\" holds the own standalone {$kind}, not one reached through its link", function () use ($kind) {
        $person = User::factory()->create();
        $own = standaloneItem($kind, $person);
        $granted = standaloneItem($kind, User::factory()->create());
        grantOn($granted, $person);

        $this->actingAs($person)->get(route('without-group.show'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('WithoutGroup/Show')
            ->has("{$kind}s", 1)
            ->where("{$kind}s.0.slug", $own->slug)
            ->where("{$kind}s.0.group_name", null));
    });

    it("F-21: changes to a standalone {$kind} refresh only the organizer and grant holders", function () use ($kind) {
        $organizer = User::factory()->create();
        [$group, $owner, $member] = dashboardGroup();
        $guest = User::factory()->create();
        $item = standaloneItem($kind, $organizer);
        grantOn($item, $guest);
        EventFacade::fake([DashboardChanged::class]);

        $this->actingAs($organizer)->patch(route("{$kind}s.update", $item), ['title' => 'Renamed', 'date' => now()->addMonth()->format('Y-m-d'), 'reset_acceptances' => true])
            ->assertSessionHasNoErrors();

        EventFacade::assertDispatched(DashboardChanged::class, fn (DashboardChanged $broadcast) => $broadcast->broadcastWith() === ['group_id' => null]
            && collect($broadcast->userIds)->sort()->values()->all() === collect([$organizer->id, $guest->id])->sort()->values()->all());
    });
}

/**
 * Create a standalone poll and event of a new organizer, both titled "Solo gig …", plus a member of an unrelated group.
 *
 * @return array{0: User, 1: User, 2: Poll, 3: Event}
 */
function soloGigItems(): array
{
    $organizer = User::factory()->create();
    [, , $member] = dashboardGroup();
    $poll = standaloneItem('poll', $organizer);
    $poll->update(['title' => 'Solo gig poll']);
    $event = standaloneItem('event', $organizer);
    $event->update(['title' => 'Solo gig event']);

    return [$organizer, $member, $poll, $event];
}

it('F-22: own standalone polls and events show on the organizer\'s dashboard only', function () {
    [$organizer, $member, $poll, $event] = soloGigItems();

    $this->actingAs($organizer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('polls.0.slug', $poll->slug)
        ->where('polls.0.group_name', null)
        ->where('waiting.0.slug', $poll->slug)
        ->where('upcoming.0.slug', $event->slug)
        ->where('upcoming.0.group_slug', null)
        ->where('withoutGroup', ['lists_count' => 0, 'polls_count' => 1, 'events_count' => 1]));
    $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('polls', 0)->has('upcoming', 0));
});

it('F-31: an own standalone poll appears on "All polls" for the organizer only', function () {
    [$organizer, $member, $poll] = soloGigItems();

    $this->actingAs($organizer)->get(route('polls.index'))->assertInertia(fn (Assert $page) => $page->has('polls', 1)->where('polls.0.slug', $poll->slug));
    $this->actingAs($member)->get(route('polls.index'))->assertInertia(fn (Assert $page) => $page->has('polls', 0));
});

it('F-32: an own standalone event appears on "All events" for the organizer only', function () {
    [$organizer, $member, , $event] = soloGigItems();

    $this->actingAs($organizer)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page->has('events', 1)->where('events.0.slug', $event->slug));
    $this->actingAs($member)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page->has('events', 0));
});

it('F-25: search finds own standalone polls and events for the organizer only', function () {
    [$organizer, $member] = soloGigItems();

    $titles = collect($this->actingAs($organizer)->getJson(route('search', ['q' => 'solo gig']))->json('sections'))->flatMap(fn ($section) => collect($section['results'])->pluck('title'))->all();

    expect($titles)->toContain('Solo gig poll', 'Solo gig event')
        ->and($this->actingAs($member)->getJson(route('search', ['q' => 'solo gig']))->json('sections'))->toBe([]);
});

it('F-12: follow-up polls, sub-events and fixed events of standalone items stay standalone', function () {
    $organizer = User::factory()->create();
    $guest = User::factory()->create();
    $event = standaloneItem('event', $organizer);
    grantOn($event, $guest);
    EventFacade::fake([DashboardChanged::class]);

    $this->actingAs($organizer)->post(route('events.polls.store', $event), [
        'title' => 'Rehearsal',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => now()->addWeek()->format('Y-m-d'), 'starts_at' => null, 'ends_at' => null, 'label' => null]],
    ])->assertSessionHasNoErrors();
    $followUp = Poll::query()->where('title', 'Rehearsal')->firstOrFail();

    $this->actingAs($organizer)->get(route('events.create-any', ['parent_event_id' => $event->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('form.parent_event_id', $event->id)->where('form.group_slug', null));
    $this->actingAs($organizer)->post(route('events.store-any'), [
        'title' => 'Soundcheck',
        'date' => now()->addWeek()->format('Y-m-d'),
        'end_date' => now()->addWeek()->addDay()->format('Y-m-d'),
        'parent_event_id' => $event->id,
    ])->assertSessionHasNoErrors();
    $child = Event::query()->where('title', 'Soundcheck')->firstOrFail();

    $poll = standaloneItem('poll', $organizer);
    $option = $poll->options->first();
    $this->actingAs($organizer)->post(route('polls.fix', $poll), ['options' => [$option->slug]])->assertSessionHas('success');
    $fixed = Event::query()->where('origin_poll_option_id', $option->id)->firstOrFail();

    expect($followUp->group_id)->toBeNull()
        ->and($followUp->event_id)->toBe($event->id)
        ->and($child->group_id)->toBeNull()
        ->and($child->parent_event_id)->toBe($event->id)
        ->and($child->end_date->format('Y-m-d'))->toBe(now()->addWeek()->addDay()->format('Y-m-d'))
        ->and($child->participations()->pluck('user_id')->all())->toBe([$organizer->id])
        ->and($fixed->group_id)->toBeNull();
    $this->actingAs($guest)->get(route('events.show', $child))->assertOk();
});

it('F-10: a sub-event of a standalone event needs a standalone parent and its management', function () {
    $organizer = User::factory()->create();
    $guest = User::factory()->create();
    $event = standaloneItem('event', $organizer);
    grantOn($event, $guest);
    [$group, $owner] = dashboardGroup();
    $groupEvent = dashboardEvent($group, $owner);
    $input = ['title' => 'Soundcheck', 'date' => now()->addWeek()->format('Y-m-d')];

    $this->actingAs($guest)->post(route('events.store-any'), [...$input, 'parent_event_id' => $event->id])->assertForbidden();
    $this->actingAs($owner)->post(route('events.store-any'), [...$input, 'parent_event_id' => $groupEvent->id])->assertSessionHasErrors('parent_event_id');

    // An outsider gets the same error for another person's standalone event as for an unknown id, so its existence stays hidden.
    $outsider = User::factory()->create();
    $hidden = $this->actingAs($outsider)->post(route('events.store-any'), [...$input, 'parent_event_id' => $event->id])->assertStatus(302)->assertSessionHasErrors('parent_event_id');
    $unknown = $this->actingAs($outsider)->post(route('events.store-any'), [...$input, 'parent_event_id' => 999999])->assertStatus(302)->assertSessionHasErrors('parent_event_id');
    expect($hidden->getSession()->get('errors')->get('parent_event_id'))->toBe($unknown->getSession()->get('errors')->get('parent_event_id'));

    expect(Event::query()->where('title', 'Soundcheck')->exists())->toBeFalse();
});

it('F-23: creating a standalone poll or event notifies no group member', function () {
    Notification::fake();
    [$group, $owner, $member] = dashboardGroup();

    $this->actingAs($owner)->post(route('polls.store-any'), [
        'title' => 'Solo poll',
        'type' => 'date_finder',
        'granularity' => 'day',
        'options' => [['date' => now()->addWeek()->format('Y-m-d'), 'starts_at' => null, 'ends_at' => null, 'label' => null]],
    ])->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('events.store-any'), ['title' => 'Solo event', 'date' => now()->addWeek()->format('Y-m-d')])->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('F-20: on a standalone event only the author deletes a comment', function () {
    $organizer = User::factory()->create();
    $guest = User::factory()->create();
    $event = standaloneItem('event', $organizer);
    grantOn($event, $guest);
    $comment = Comment::query()->forceCreate(['commentable_type' => 'event', 'commentable_id' => $event->id, 'user_id' => $guest->id, 'body' => 'Hi']);

    $this->actingAs($organizer)->delete(route('events.comments.destroy', [$event, $comment]))->assertForbidden();
    $this->actingAs($guest)->delete(route('events.comments.destroy', [$event, $comment]))->assertRedirect();

    expect(Comment::query()->whereKey($comment->id)->exists())->toBeFalse();
});

it('F-29: deleting a standalone poll or event returns to the dashboard', function (string $kind) {
    $organizer = User::factory()->create();
    $item = standaloneItem($kind, $organizer);

    $this->actingAs($organizer)->delete(route("{$kind}s.destroy", $item))->assertRedirect(route('dashboard'))->assertSessionHas('success');

    expect($item::query()->whereKey($item->id)->exists())->toBeFalse();
})->with(['poll', 'event']);

it('F-12/F-26: a follow-up poll of a standalone event opens to link guests only after accepting', function () {
    $organizer = User::factory()->create();
    $guest = User::factory()->create();
    $event = standaloneItem('event', $organizer);
    grantOn($event, $guest);
    $followUp = dashboardPoll(null, $organizer, 1, null, 0, ['event_id' => $event->id]);

    $this->actingAs($guest)->get(route('polls.show', $followUp))->assertNotFound();

    $this->actingAs($guest)->put(route('events.participation.update', $event), ['status' => 'accepted'])->assertSessionHasNoErrors();

    $this->actingAs($guest)->get(route('polls.show', $followUp))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('polls.show', $followUp))->assertNotFound();
});

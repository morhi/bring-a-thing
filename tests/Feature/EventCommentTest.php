<?php

use App\Actions\Comments\PostComment;
use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Events\CommentAdded;
use App\Events\CommentDeleted;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create an event whose organizer is a plain member, in a group with an owner, an admin and two more plain members.
 *
 * Both plain members take part with "accepted".
 *
 * @return array{event: Event, owner: User, admin: User, organizer: User, author: User, member: User}
 */
function commentedEvent(): array
{
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $organizer = User::factory()->create();
    $author = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());

    foreach ([$organizer, $author, $member] as $plainMember) {
        $group->addMember($plainMember, GroupRole::Member, now());
    }

    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);

    foreach ([$organizer, $author, $member] as $participant) {
        EventParticipation::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $participant->id]);
    }

    return compact('event', 'owner', 'admin', 'organizer', 'author', 'member');
}

it('F-20: a comment on an item appears only on the item, not in the list\'s thread', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $this->actingAs($owner)->post(route('rosters.items.comments.store', [$roster, $item]), ['body' => 'Vegan please'])->assertRedirect();

    $this->actingAs($owner)->get(route('rosters.show', $roster))->assertInertia(fn (Assert $page) => $page
        ->component('Rosters/Show', false)
        ->has('page.comments', 0)
        ->has('page.items.0.comments', 1)
        ->where('page.items.0.comments.0.body', 'Vegan please'));
});

it('F-20: a comment on an event appears only on that event', function () {
    ['event' => $event, 'author' => $author] = commentedEvent();
    $otherEvent = Event::factory()->create(['group_id' => $event->group_id, 'organizer_id' => $author->id]);
    EventParticipation::factory()->accepted()->create(['event_id' => $otherEvent->id, 'user_id' => $author->id]);

    $this->actingAs($author)
        ->post(route('events.comments.store', $event), ['body' => 'Who brings the PA?'])
        ->assertSessionHas('success', 'Comment posted');

    $this->assertDatabaseHas('comments', ['commentable_type' => 'event', 'commentable_id' => $event->id, 'user_id' => $author->id]);
    $this->actingAs($author)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->has('page.comments', 1)
        ->where('page.comments.0.body', 'Who brings the PA?')
        ->where('page.comments.0.can_delete', true));
    $this->actingAs($author)->get(route('events.show', $otherEvent))->assertInertia(fn (Assert $page) => $page->has('page.comments', 0));
});

it('F-20: a member who is neither author, group owner nor admin sees no delete action on an event comment', function () {
    ['event' => $event, 'author' => $author, 'member' => $member, 'organizer' => $organizer] = commentedEvent();
    $comment = (new PostComment)->handle($event, $author, 'See you there');

    foreach ([$member, $organizer] as $viewer) {
        $this->actingAs($viewer)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('page.comments.0.id', $comment->id)
            ->where('page.comments.0.can_delete', false));
        $this->actingAs($viewer)->delete(route('events.comments.destroy', [$event, $comment]))->assertForbidden();
    }

    $this->assertModelExists($comment);
});

it('lets the author, the group owner and admins delete an event comment and broadcasts the removal', function () {
    EventFacade::fake([CommentDeleted::class]);
    ['event' => $event, 'author' => $author, 'owner' => $owner, 'admin' => $admin] = commentedEvent();

    foreach ([$author, $owner, $admin] as $deleter) {
        $comment = (new PostComment)->handle($event, $author, 'Typo');

        $this->actingAs($deleter)
            ->delete(route('events.comments.destroy', [$event, $comment]))
            ->assertSessionHas('success', 'Comment removed');

        $this->assertModelMissing($comment);
    }

    EventFacade::assertDispatchedTimes(CommentDeleted::class, 3);
    EventFacade::assertDispatched(CommentDeleted::class, fn (CommentDeleted $broadcast) => $broadcast->broadcastOn()->name === 'private-event.'.$event->id);
});

it('broadcasts a new event comment on the event channel with ids only', function () {
    EventFacade::fake([CommentAdded::class]);
    ['event' => $event, 'author' => $author] = commentedEvent();

    $this->actingAs($author)->post(route('events.comments.store', $event), ['body' => 'Hi'])->assertRedirect();

    $comment = Comment::query()->sole();
    EventFacade::assertDispatched(CommentAdded::class, fn (CommentAdded $broadcast) => $broadcast->broadcastOn()->name === 'private-event.'.$event->id
        && $broadcast->broadcastAs() === 'comment.added'
        && $broadcast->broadcastWith() === ['comment_id' => $comment->id, 'commentable_type' => 'event', 'commentable_id' => $event->id]);
});

it('hides the event thread from members who declined and rejects their comments', function () {
    ['event' => $event, 'author' => $author, 'member' => $member] = commentedEvent();
    $event->participations()->where('user_id', $member->id)->update(['status' => ParticipationStatus::Declined]);
    (new PostComment)->handle($event, $author, 'Hi');

    $this->actingAs($member)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
        ->has('page.comments', 0)
        ->where('page.permissions.can_comment', false));
    $this->actingAs($member)->post(route('events.comments.store', $event), ['body' => 'Hi'])->assertForbidden();
});

it('returns 404 to outsiders posting on an event and for comments of another event', function () {
    ['event' => $event, 'author' => $author] = commentedEvent();
    $otherEvent = Event::factory()->create(['group_id' => $event->group_id]);
    $comment = (new PostComment)->handle($otherEvent, $author, 'Elsewhere');

    $this->actingAs(User::factory()->create())->post(route('events.comments.store', $event), ['body' => 'Hi'])->assertNotFound();
    $this->actingAs($author)->delete(route('events.comments.destroy', [$event, $comment]))->assertNotFound();
});

it('removes the event comments when the event is deleted', function () {
    ['event' => $event, 'author' => $author, 'owner' => $owner] = commentedEvent();
    $comment = (new PostComment)->handle($event, $author, 'Hi');

    $this->actingAs($owner)->delete(route('events.destroy', $event))->assertRedirect();

    $this->assertModelMissing($comment);
});

it('lets the list owner but not the event organizer delete comments on an event list', function () {
    ['event' => $event, 'author' => $author, 'organizer' => $organizer, 'member' => $listOwner] = commentedEvent();
    $roster = Roster::factory()->create(['owner_id' => $listOwner->id, 'event_id' => $event->id]);
    $comment = (new PostComment)->handle($roster, $author, 'Hi');

    $this->actingAs($organizer)->delete(route('rosters.comments.destroy', [$roster, $comment]))->assertForbidden();
    $this->actingAs($listOwner)->delete(route('rosters.comments.destroy', [$roster, $comment]))->assertRedirect();

    $this->assertModelMissing($comment);
});

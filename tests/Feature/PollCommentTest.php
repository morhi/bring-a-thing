<?php

use App\Actions\Comments\PostComment;
use App\Enums\GroupRole;
use App\Events\CommentAdded;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Create a poll whose organizer is a plain member, in a group with an owner, an admin and two more plain members.
 *
 * @return array{poll: Poll, owner: User, admin: User, organizer: User, author: User, member: User}
 */
function commentedPoll(): array
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

    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $organizer->id]);

    return compact('poll', 'owner', 'admin', 'organizer', 'author', 'member');
}

it('F-20: a comment on a poll appears only on that poll', function () {
    ['poll' => $poll, 'author' => $author] = commentedPoll();
    $otherPoll = Poll::factory()->create(['group_id' => $poll->group_id, 'organizer_id' => $author->id]);

    $this->actingAs($author)
        ->post(route('polls.comments.store', $poll), ['body' => 'Is Friday an option?'])
        ->assertSessionHas('success', 'Comment posted');

    $this->assertDatabaseHas('comments', ['commentable_type' => 'poll', 'commentable_id' => $poll->id, 'user_id' => $author->id]);
    $this->actingAs($author)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $page
        ->has('page.comments', 1)
        ->where('page.comments.0.body', 'Is Friday an option?')
        ->where('page.comments.0.can_delete', true));
    $this->actingAs($author)->get(route('polls.show', $otherPoll))->assertInertia(fn (Assert $page) => $page->has('page.comments', 0));
});

it('F-20: a comment on a poll is broadcast on the poll channel with ids only', function () {
    EventFacade::fake([CommentAdded::class]);
    ['poll' => $poll, 'author' => $author] = commentedPoll();

    $this->actingAs($author)->post(route('polls.comments.store', $poll), ['body' => 'Hi'])->assertRedirect();

    EventFacade::assertDispatched(CommentAdded::class, fn (CommentAdded $broadcast) => $broadcast->broadcastOn()->name === 'private-poll.'.$poll->id
        && $broadcast->broadcastWith()['commentable_type'] === 'poll');
});

it('F-20: anyone who may view the poll comments, on a closed poll too', function () {
    ['poll' => $poll, 'member' => $member] = commentedPoll();
    $poll->forceFill(['closed_at' => now()])->save();

    $this->actingAs($member)->post(route('polls.comments.store', $poll), ['body' => 'Late thought'])->assertRedirect();

    $this->assertDatabaseHas('comments', ['commentable_id' => $poll->id, 'commentable_type' => 'poll', 'body' => 'Late thought']);
});

it('F-20: someone who may not view the poll cannot read or write its thread', function () {
    ['poll' => $poll, 'author' => $author] = commentedPoll();
    $comment = (new PostComment)->handle($poll, $author, 'Hi');
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->post(route('polls.comments.store', $poll), ['body' => 'Hi'])->assertNotFound();
    $this->actingAs($outsider)->delete(route('polls.comments.destroy', [$poll, $comment]))->assertNotFound();
    $this->actingAs($outsider)->get(route('polls.show', $poll))->assertNotFound();
    $this->assertDatabaseCount('comments', 1);
});

it('F-20: the author, group owner and admins delete a poll comment, the organizer and other members cannot', function () {
    ['poll' => $poll, 'author' => $author, 'owner' => $owner, 'admin' => $admin, 'organizer' => $organizer, 'member' => $member] = commentedPoll();

    foreach ([$organizer, $member] as $viewer) {
        $comment = (new PostComment)->handle($poll, $author, 'Keep me');

        $this->actingAs($viewer)->get(route('polls.show', $poll))->assertInertia(fn (Assert $page) => $page
            ->where('page.comments.0.id', $comment->id)
            ->where('page.comments.0.can_delete', false));
        $this->actingAs($viewer)->delete(route('polls.comments.destroy', [$poll, $comment]))->assertForbidden();
        $this->assertModelExists($comment);
        $comment->delete();
    }

    foreach ([$author, $owner, $admin] as $deleter) {
        $comment = (new PostComment)->handle($poll, $author, 'Typo');

        $this->actingAs($deleter)
            ->delete(route('polls.comments.destroy', [$poll, $comment]))
            ->assertSessionHas('success', 'Comment removed');

        $this->assertModelMissing($comment);
    }
});

it('F-20: on a standalone poll only the author deletes a comment', function () {
    $organizer = User::factory()->create();
    $guest = User::factory()->create();
    $poll = Poll::factory()->create(['group_id' => null, 'organizer_id' => $organizer->id]);
    grantOn($poll, $guest);
    $comment = (new PostComment)->handle($poll, $guest, 'My remark');

    $this->actingAs($organizer)->delete(route('polls.comments.destroy', [$poll, $comment]))->assertForbidden();
    $this->assertModelExists($comment);

    $this->actingAs($guest)->delete(route('polls.comments.destroy', [$poll, $comment]))->assertRedirect();
    $this->assertModelMissing($comment);
});

it('F-20: a comment of another poll is not found under this poll and deleting a poll removes its comments', function () {
    ['poll' => $poll, 'author' => $author, 'owner' => $owner] = commentedPoll();
    $otherPoll = Poll::factory()->create(['group_id' => $poll->group_id, 'organizer_id' => $author->id]);
    $comment = (new PostComment)->handle($otherPoll, $author, 'Elsewhere');

    $this->actingAs($author)->delete(route('polls.comments.destroy', [$poll, $comment]))->assertNotFound();

    $otherPoll->delete();

    $this->assertModelMissing($comment);
});

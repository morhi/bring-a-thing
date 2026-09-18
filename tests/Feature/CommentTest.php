<?php

use App\Actions\Comments\PostComment;
use App\Enums\GroupRole;
use App\Events\CommentDeleted;
use App\Events\CommentPosted;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

function postTestComment(Model $commentable, User $user, string $body = 'Hi'): Comment
{
    return (new PostComment)->handle($commentable, $user, $body);
}

/**
 * Create a group-attached roster with the owner and the given members joined.
 *
 * @param  array<int, User>  $members
 */
function rosterWithGroupMembers(User $owner, array $members): Roster
{
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    return Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
}

it('lets a member post a list-level comment', function () {
    Event::fake([CommentPosted::class]);
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, [$member]);

    $response = $this->actingAs($member)->post(route('rosters.comments.store', $roster), ['body' => 'Looking forward to this!']);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Comment posted.');
    $this->assertDatabaseHas('comments', [
        'commentable_type' => 'roster',
        'commentable_id' => $roster->id,
        'user_id' => $member->id,
        'body' => 'Looking forward to this!',
    ]);
    Event::assertDispatched(CommentPosted::class, fn (CommentPosted $event) => $event->rosterId === $roster->id);
});

it('lets a member post an item-level comment', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, [$member]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $response = $this->actingAs($member)->post(route('rosters.items.comments.store', [$roster, $item]), ['body' => 'Can bring a vegan version instead?']);

    $response->assertRedirect();
    $this->assertDatabaseHas('comments', [
        'commentable_type' => 'roster_item',
        'commentable_id' => $item->id,
        'user_id' => $member->id,
    ]);
});

it('forbids a non-member from posting a comment', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, []);

    $this->actingAs($outsider)->post(route('rosters.comments.store', $roster), ['body' => 'Hi'])->assertForbidden();
});

it('lets a comment author delete their own comment', function () {
    Event::fake([CommentDeleted::class]);
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, [$member]);
    $comment = postTestComment($roster, $member);

    $response = $this->actingAs($member)->delete(route('rosters.comments.destroy', [$roster, $comment]));

    $response->assertRedirect();
    $this->assertModelMissing($comment);
    Event::assertDispatched(CommentDeleted::class);
});

it('lets the roster owner delete another member’s comment', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, [$member]);
    $comment = postTestComment($roster, $member);

    $this->actingAs($owner)->delete(route('rosters.comments.destroy', [$roster, $comment]))->assertRedirect();
    $this->assertModelMissing($comment);
});

it('forbids a plain member from deleting another member’s comment', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, [$first, $second]);
    $comment = postTestComment($roster, $first);

    $this->actingAs($second)->delete(route('rosters.comments.destroy', [$roster, $comment]))->assertForbidden();
});

it('returns 404 deleting a comment that belongs to a different roster', function () {
    $owner = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, []);
    $otherRoster = rosterWithGroupMembers($owner, []);
    $comment = postTestComment($otherRoster, $owner);

    $this->actingAs($owner)->delete(route('rosters.comments.destroy', [$roster, $comment]))->assertNotFound();
});

it('returns 404 deleting an item comment through the wrong item', function () {
    $owner = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, []);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $otherItem = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $comment = postTestComment($item, $owner);

    $this->actingAs($owner)->delete(route('rosters.items.comments.destroy', [$roster, $otherItem, $comment]))->assertNotFound();
});

it('broadcasts posted and deleted comment events on the roster private channel', function () {
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');

    $owner = User::factory()->create();
    $roster = rosterWithGroupMembers($owner, []);
    $comment = Comment::factory()->create(['commentable_type' => 'roster', 'commentable_id' => $roster->id, 'user_id' => $owner->id]);

    $postedEvent = new CommentPosted($comment, $roster->id);
    expect($postedEvent->broadcastOn()->name)->toBe('private-roster.'.$roster->id);
    expect($postedEvent->broadcastAs())->toBe('comment.posted');

    $deletedEvent = new CommentDeleted($roster->id, $comment->id, 'roster', $roster->id);
    expect($deletedEvent->broadcastOn()->name)->toBe('private-roster.'.$roster->id);
    expect($deletedEvent->broadcastAs())->toBe('comment.deleted');
});

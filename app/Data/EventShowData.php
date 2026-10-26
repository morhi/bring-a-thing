<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\RosterItemClaim;
use App\Models\User;
use Spatie\LaravelData\Data;

class EventShowData extends Data
{
    /**
     * @param  array<int, ParticipationData>  $participants
     * @param  array<int, EventClaimData>  $own_claims
     * @param  array<int, GroupListData>  $lists
     * @param  array<int, GroupPollData>  $follow_up_polls
     * @param  array<int, GroupEventData>  $child_events
     * @param  array<int, CommentData>  $comments
     */
    public function __construct(
        public EventData $event,
        public ?ParticipationStatus $own_status,
        public int $accepted_count,
        public int $pending_count,
        public int $declined_count,
        public array $participants,
        public array $own_claims,
        public array $lists,
        public array $follow_up_polls,
        public int $follow_up_audience_count,
        public array $child_events,
        public array $comments,
        public ?EventDeleteCountsData $delete_counts,
        public EventPermissionsData $permissions,
    ) {}

    /**
     * Build the event page for the given member (F-10, F-11).
     *
     * participants lists every group member, with a null status for those
     * without participation, plus former members who keep a status (F-04).
     * Lists are only for participants ("accepted" or "pending"), follow-up
     * polls only for their audience: "accepted" and managers (F-12);
     * everyone else gets the key details. follow_up_audience_count feeds
     * the audience note of "New follow-up poll".
     * own_claims feeds the decline confirmation. comments is the event's
     * own thread, only for those who may comment (F-20). delete_counts
     * feeds the delete confirmation, only for those who may delete.
     */
    public static function forUser(Event $event, User $user): self
    {
        $event->loadMissing('group.members', 'organizer', 'originPollOption.poll', 'parentEvent', 'participations.user', 'childEvents.participations', 'rosters.items.claims', 'polls.options.responses', 'comments.user');

        $statuses = $event->participations->keyBy('user_id');
        $participants = $event->group->members
            ->map(fn (User $member) => new ParticipationData($member->id, $member->name, $statuses->get($member->id)?->status))
            ->concat($event->participations
                ->reject(fn (EventParticipation $participation) => $event->group->members->contains('id', $participation->user_id))
                ->map(fn (EventParticipation $participation) => new ParticipationData($participation->user_id, $participation->user?->name, $participation->status)))
            ->values();

        $isParticipant = $event->hasParticipant($user);
        $followUpPolls = $event->polls
            ->each(fn (Poll $poll) => $poll->setRelation('event', $event)->setRelation('group', $event->group))
            ->filter(fn (Poll $poll) => $poll->includesMember($user));

        $permissions = EventPermissionsData::forUser($event, $user);

        return new self(
            event: EventData::fromEvent($event),
            own_status: $event->statusOf($user),
            accepted_count: $event->participations->where('status', ParticipationStatus::Accepted)->count(),
            pending_count: $event->participations->where('status', ParticipationStatus::Pending)->count(),
            declined_count: $event->participations->where('status', ParticipationStatus::Declined)->count(),
            participants: $participants->all(),
            own_claims: $event->claimsOf($user)->map(fn (RosterItemClaim $claim) => EventClaimData::fromClaim($claim))->all(),
            lists: $isParticipant ? $event->rosters->map(fn (Roster $roster) => GroupListData::fromRoster($roster))->all() : [],
            follow_up_polls: $followUpPolls->sortBy(fn (Poll $poll) => $poll->isClosed() ? 1 : 0)->map(fn (Poll $poll) => GroupPollData::fromPoll($poll))->values()->all(),
            follow_up_audience_count: $event->followUpAudience()->count(),
            child_events: $event->childEvents->sortBy(fn (Event $child) => $child->date->format('Y-m-d').' '.($child->starts_at ?? '00:00'))->map(fn (Event $child) => GroupEventData::forUser($child, $user))->values()->all(),
            comments: $permissions->can_comment ? $event->comments->map(fn (Comment $comment) => CommentData::forUser($comment->setRelation('commentable', $event), $user))->all() : [],
            delete_counts: $permissions->can_delete ? EventDeleteCountsData::fromEvent($event) : null,
            permissions: $permissions,
        );
    }
}

<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
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
     * @param  array<int, EventLinkData>  $lists
     * @param  array<int, EventLinkData>  $follow_up_polls
     * @param  array<int, EventSummaryData>  $child_events
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
     * own_claims feeds the decline confirmation.
     */
    public static function forUser(Event $event, User $user): self
    {
        $event->loadMissing('group.members', 'organizer', 'originPollOption.poll', 'parentEvent', 'participations.user', 'childEvents', 'rosters', 'polls');

        $statuses = $event->participations->keyBy('user_id');
        $participants = $event->group->members
            ->map(fn (User $member) => new ParticipationData($member->id, $member->name, $statuses->get($member->id)?->status))
            ->concat($event->participations
                ->reject(fn (EventParticipation $participation) => $event->group->members->contains('id', $participation->user_id))
                ->map(fn (EventParticipation $participation) => new ParticipationData($participation->user_id, $participation->user?->name, $participation->status)))
            ->values();

        $isParticipant = $event->hasParticipant($user);
        $followUpPolls = $event->polls
            ->each(fn (Poll $poll) => $poll->setRelation('event', $event))
            ->filter(fn (Poll $poll) => $poll->includesMember($user));

        return new self(
            event: EventData::fromEvent($event),
            own_status: $event->statusOf($user),
            accepted_count: $event->participations->where('status', ParticipationStatus::Accepted)->count(),
            pending_count: $event->participations->where('status', ParticipationStatus::Pending)->count(),
            declined_count: $event->participations->where('status', ParticipationStatus::Declined)->count(),
            participants: $participants->all(),
            own_claims: $event->claimsOf($user)->map(fn (RosterItemClaim $claim) => EventClaimData::fromClaim($claim))->all(),
            lists: $isParticipant ? $event->rosters->map(fn (Roster $roster) => new EventLinkData($roster->slug, $roster->title))->all() : [],
            follow_up_polls: $followUpPolls->map(fn (Poll $poll) => new EventLinkData($poll->slug, $poll->title))->values()->all(),
            follow_up_audience_count: $event->followUpAudience()->count(),
            child_events: $event->childEvents->map(fn (Event $child) => EventSummaryData::fromEvent($child))->all(),
            permissions: EventPermissionsData::forUser($event, $user),
        );
    }
}

<?php

namespace App\Data;

use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\LaravelData\Data;

class GroupShowData extends Data
{
    /**
     * @param  array<int, GroupMemberData>  $members
     * @param  array<int, GroupPollData>  $polls
     * @param  array<int, GroupEventData>  $events
     * @param  array<int, GroupListData>  $lists
     * @param  array<int, InviteFriendData>  $friends
     */
    public function __construct(
        public string $slug,
        public string $name,
        public GroupRole $own_role,
        public array $members,
        public array $polls,
        public array $events,
        public array $lists,
        public GroupPermissionsData $permissions,
        public ?GroupInviteLinkData $invite_link,
        public array $friends,
    ) {}

    /**
     * Build the group page for a member (F-03, F-04, F-06, F-10, F-13).
     *
     * Polls: open first, newest first; follow-up polls only for their
     * audience (F-12). Events: upcoming by start, then past with the most
     * recent first. Lists: group lists only, event lists live in the event.
     * Invite link and friend suggestions only reach owners and admins.
     */
    public static function forUser(Group $group, User $user): self
    {
        $group->load([
            'members',
            'polls' => fn (HasMany $polls) => $polls->visibleTo($user)->with('event.participations', 'options.responses', 'options.event')->latest(),
            'events' => fn (HasMany $events) => $events->with('participations'),
            'rosters' => fn (HasMany $rosters) => $rosters->whereNull('event_id')->with('items.claims')->latest(),
        ]);

        $polls = $group->polls
            ->each(fn (Poll $poll) => $poll->setRelation('group', $group))
            ->sortBy(fn (Poll $poll) => $poll->isClosed() ? 1 : 0)
            ->map(fn (Poll $poll) => GroupPollData::fromPoll($poll));

        [$past, $upcoming] = $group->events->partition(fn (Event $event) => $event->isPast());
        $startOf = fn (Event $event) => $event->date->format('Y-m-d').' '.($event->starts_at ?? '00:00');
        $events = $upcoming->sortBy($startOf)
            ->concat($past->sortByDesc($startOf))
            ->map(fn (Event $event) => GroupEventData::forUser($event, $user));

        $canInvite = $user->can('invite', $group);

        return new self(
            slug: $group->slug,
            name: $group->name,
            own_role: $group->members->firstWhere('id', $user->getKey())->pivot->role,
            members: GroupMemberData::listOf($group->members),
            polls: $polls->values()->all(),
            events: $events->values()->all(),
            lists: $group->rosters->map(fn (Roster $roster) => GroupListData::fromRoster($roster))->all(),
            permissions: GroupPermissionsData::forUser($group, $user),
            invite_link: $user->can('manageInviteLink', $group) ? GroupInviteLinkData::fromGroup($group) : null,
            friends: $canInvite ? InviteFriendData::listFor($user) : [],
        );
    }
}

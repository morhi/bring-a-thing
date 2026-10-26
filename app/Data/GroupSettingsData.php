<?php

namespace App\Data;

use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupSettingsData extends Data
{
    /**
     * @param  array<int, GroupMemberData>  $members
     * @param  array<int, InviteFriendData>  $friends
     */
    public function __construct(
        public string $slug,
        public string $name,
        public bool $members_can_set_status,
        public array $members,
        public GroupInviteLinkData $invite_link,
        public GroupSettingsPermissionsData $permissions,
        public array $friends,
        public int $poll_count,
        public int $event_count,
        public int $list_count,
    ) {}

    /**
     * Build the group settings page for an owner or admin (F-03, F-04).
     *
     * The counts feed the delete confirmation, which names what goes with the group.
     */
    public static function forUser(Group $group, User $user): self
    {
        $group->load('members')->loadCount(['polls', 'events', 'rosters']);

        return new self(
            slug: $group->slug,
            name: $group->name,
            members_can_set_status: $group->members_can_set_status,
            members: GroupMemberData::listOf($group->members),
            invite_link: GroupInviteLinkData::fromGroup($group),
            permissions: GroupSettingsPermissionsData::forUser($group, $user),
            friends: InviteFriendData::listFor($user),
            poll_count: (int) $group->polls_count,
            event_count: (int) $group->events_count,
            list_count: (int) $group->rosters_count,
        );
    }
}

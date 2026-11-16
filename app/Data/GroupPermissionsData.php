<?php

namespace App\Data;

use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupPermissionsData extends Data
{
    public function __construct(
        public bool $can_manage_settings,
        public bool $can_invite,
        public bool $can_create,
        public bool $can_leave,
        public bool $can_request_membership,
        public bool $has_pending_membership_request,
    ) {}

    /**
     * Resolve which group page actions a member may see (F-03, F-04).
     *
     * Every member creates polls, events and lists in the group.
     */
    public static function forUser(Group $group, User $user): self
    {
        return new self(
            can_manage_settings: $group->isAtLeastAdmin($user),
            can_invite: $user->can('invite', $group),
            can_create: true,
            can_leave: $user->can('leave', $group),
            can_request_membership: false,
            has_pending_membership_request: false,
        );
    }

    /**
     * Deny every group page action to a link guest, except asking for membership (F-03, F-26, F-27).
     *
     * A pending request replaces the action with a "Request sent" state.
     */
    public static function forGuest(Group $group, User $user): self
    {
        return new self(
            can_manage_settings: false,
            can_invite: false,
            can_create: false,
            can_leave: false,
            can_request_membership: $user->can('requestMembership', $group),
            has_pending_membership_request: $group->membershipRequests()->where('user_id', $user->getKey())->exists(),
        );
    }
}

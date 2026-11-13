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
        );
    }

    /**
     * Deny every group page action to a link guest (F-26, F-27).
     */
    public static function forGuest(): self
    {
        return new self(can_manage_settings: false, can_invite: false, can_create: false);
    }
}

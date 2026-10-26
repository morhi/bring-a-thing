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
    ) {}

    /**
     * Resolve which group page actions the user may see (F-03, F-04).
     */
    public static function forUser(Group $group, User $user): self
    {
        return new self(
            can_manage_settings: $group->isAtLeastAdmin($user),
            can_invite: $user->can('invite', $group),
        );
    }
}

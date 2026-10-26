<?php

namespace App\Data;

use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupSettingsPermissionsData extends Data
{
    public function __construct(
        public bool $can_rename,
        public bool $can_switch_option,
        public bool $can_change_roles,
        public bool $can_delete,
    ) {}

    /**
     * Resolve which owner-only settings actions the user may see (F-03).
     */
    public static function forUser(Group $group, User $user): self
    {
        return new self(
            can_rename: $user->can('update', $group),
            can_switch_option: $user->can('switchOption', $group),
            can_change_roles: $group->owner_id === $user->getKey(),
            can_delete: $user->can('delete', $group),
        );
    }
}

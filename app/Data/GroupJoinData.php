<?php

namespace App\Data;

use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupJoinData extends Data
{
    public function __construct(
        public string $token,
        public string $group_name,
        public bool $locked,
        public int $member_count,
        public bool $is_authenticated,
        public bool $needs_email,
    ) {}

    /**
     * Build the join page state for an invite link (F-04).
     *
     * needs_email is true when joining requires an email login first: a
     * guest on a closed link, or a logged-in account that has no email yet.
     */
    public static function fromGroup(Group $group, ?User $user): self
    {
        return new self(
            token: (string) $group->invite_token,
            group_name: $group->name,
            locked: $group->invite_link_locked,
            member_count: $group->members()->count(),
            is_authenticated: $user !== null,
            needs_email: $group->invite_link_locked && $user?->email === null,
        );
    }
}

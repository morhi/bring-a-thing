<?php

namespace App\Data;

use App\Models\Group;
use Spatie\LaravelData\Data;

class GroupInviteLinkData extends Data
{
    public function __construct(
        public ?string $url,
        public bool $locked,
    ) {}

    /**
     * Build the invite link state for owners and admins (F-04).
     *
     * A null url means no link exists yet; "Copy invite link" creates it.
     */
    public static function fromGroup(Group $group): self
    {
        return new self(
            url: $group->inviteUrl(),
            locked: $group->invite_link_locked,
        );
    }
}

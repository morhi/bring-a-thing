<?php

namespace App\Data;

use App\Models\Friend;
use Spatie\LaravelData\Data;

class FriendData extends Data
{
    /**
     * @param  array<int, string>  $shared_groups
     */
    public function __construct(
        public string $slug,
        public ?string $name,
        public ?string $email,
        public bool $invitation_pending,
        public array $shared_groups,
    ) {}

    /**
     * Build one row of the friends page (F-05).
     *
     * The name given when inviting wins over the account's own name. A friend
     * whose account never followed a login link carries "Invitation pending".
     *
     * @param  array<int, string>  $sharedGroups
     */
    public static function fromFriend(Friend $friend, array $sharedGroups): self
    {
        $account = $friend->friendUser;

        return new self(
            slug: $friend->slug,
            name: $friend->name ?? $account?->name,
            email: $account?->email,
            invitation_pending: $account?->email_verified_at === null,
            shared_groups: $sharedGroups,
        );
    }
}

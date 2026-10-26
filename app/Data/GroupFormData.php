<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class GroupFormData extends Data
{
    /**
     * @param  array<int, InviteFriendData>  $friends
     */
    public function __construct(
        public array $friends,
    ) {}

    /**
     * Build the "New group" page: the friend suggestions for the invitations (F-03, F-04).
     */
    public static function forUser(User $user): self
    {
        return new self(
            friends: InviteFriendData::listFor($user),
        );
    }
}

<?php

namespace App\Data;

use App\Models\Friend;
use App\Models\User;
use Spatie\LaravelData\Data;

class InviteFriendData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public string $email,
    ) {}

    /**
     * Build the friend suggestions of the invite field (F-04, F-05).
     *
     * id is the friend's account id, so the field can tell friends that are
     * already members. Friends without an email cannot be invited and are left out.
     *
     * @return array<int, self>
     */
    public static function listFor(User $user): array
    {
        return $user->friends
            ->filter(fn (Friend $friend) => $friend->friendUser?->email !== null)
            ->map(fn (Friend $friend) => new self(
                id: $friend->friend_user_id,
                name: $friend->name ?? $friend->friendUser->name,
                email: (string) $friend->friendUser->email,
            ))
            ->sortBy(fn (self $friend) => mb_strtolower($friend->name ?? $friend->email))
            ->values()
            ->all();
    }
}

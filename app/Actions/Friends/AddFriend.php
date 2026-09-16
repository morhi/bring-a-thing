<?php

namespace App\Actions\Friends;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Models\Friend;
use App\Models\User;

class AddFriend
{
    public function __construct(
        private readonly FindOrCreateUserByEmail $findOrCreateUser,
    ) {}

    /**
     * Find or create a user by email and save it as a friend of the owner.
     *
     * Reuses the same shadow-account path as group invites, so a friend
     * entry always resolves to a real account. Re-adding an existing
     * friend is a no-op rather than a duplicate.
     */
    public function handle(User $owner, string $email, ?string $name = null): Friend
    {
        $friendUser = $this->findOrCreateUser->handle($email, $name);

        $friend = Friend::query()
            ->where('user_id', $owner->getKey())
            ->where('friend_user_id', $friendUser->getKey())
            ->first();

        if ($friend !== null) {
            return $friend;
        }

        $friend = new Friend(['name' => $name]);
        $friend->user_id = $owner->getKey();
        $friend->friend_user_id = $friendUser->getKey();
        $friend->save();

        return $friend;
    }
}

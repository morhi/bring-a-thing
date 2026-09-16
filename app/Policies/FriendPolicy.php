<?php

namespace App\Policies;

use App\Models\Friend;
use App\Models\User;

class FriendPolicy
{
    /**
     * Determine whether the user can delete the friend entry.
     */
    public function delete(User $user, Friend $friend): bool
    {
        return $friend->user_id === $user->getKey();
    }
}

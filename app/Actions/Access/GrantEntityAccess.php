<?php

namespace App\Actions\Access;

use App\Enums\GroupJoinMethod;
use App\Models\Event;
use App\Models\Poll;
use App\Models\User;

class GrantEntityAccess
{
    /**
     * Give the user access to the event or poll through its invite link (F-26, F-27).
     *
     * Returns false when the user already holds a grant on it, so joining
     * twice through the same link changes nothing. Joining a poll is an
     * explicit re-join, so it lifts an earlier withdrawal (F-08, F-27).
     */
    public function handle(Event|Poll $entity, User $user): bool
    {
        if ($entity instanceof Poll) {
            $entity->withdrawals()->where('user_id', $user->getKey())->delete();
        }

        $alreadyGranted = $entity->accessGrants()->where('user_id', $user->getKey())->exists();

        if ($alreadyGranted) {
            return false;
        }

        $entity->accessGrants()->create([
            'user_id' => $user->getKey(),
            'joined_via' => GroupJoinMethod::Link,
        ]);

        return true;
    }
}

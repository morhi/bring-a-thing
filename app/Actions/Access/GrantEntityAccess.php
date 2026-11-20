<?php

namespace App\Actions\Access;

use App\Enums\GroupJoinMethod;
use App\Models\Event;
use App\Models\Poll;
use App\Models\User;

class GrantEntityAccess
{
    public function __construct(private readonly ResolveTopmostAncestor $topmost) {}

    /**
     * Give the user access to the event or poll through its invite link (F-26, F-27).
     *
     * The user gets a grant on the topmost ancestor and on the shared item
     * itself, also when an ancestor grant already admits them. Returns false
     * when both grants exist already, so joining twice changes nothing.
     * Joining a poll is an explicit re-join, so it lifts an earlier
     * withdrawal of that poll only (F-08, F-27).
     */
    public function handle(Event|Poll $entity, User $user): bool
    {
        if ($entity instanceof Poll) {
            $entity->withdrawals()->where('user_id', $user->getKey())->delete();
        }

        $topmost = $this->topmost->handle($entity);
        $createdTopmost = $this->grant($topmost, $user);

        if ($topmost->is($entity)) {
            return $createdTopmost;
        }

        return $this->grant($entity, $user) || $createdTopmost;
    }

    private function grant(Event|Poll $item, User $user): bool
    {
        $alreadyGranted = $item->accessGrants()->where('user_id', $user->getKey())->exists();

        if ($alreadyGranted) {
            return false;
        }

        $item->accessGrants()->create([
            'user_id' => $user->getKey(),
            'joined_via' => GroupJoinMethod::Link,
        ]);

        return true;
    }
}

<?php

namespace App\Actions\Groups;

use App\Actions\Notifications\SendInAppNotification;
use App\Models\Group;
use App\Models\User;
use App\Notifications\GroupMembershipRequested;

class RequestGroupMembership
{
    public function __construct(private readonly SendInAppNotification $sendNotification) {}

    /**
     * Store a guest's request for membership and tell the owner (F-03, F-23).
     *
     * The caller checks the policy; the unique key still keeps a double
     * submit from storing a second row.
     */
    public function handle(Group $group, User $guest): void
    {
        $group->membershipRequests()->firstOrCreate(['user_id' => $guest->getKey()]);

        $this->sendNotification->handle(new GroupMembershipRequested($group, $guest));
    }
}

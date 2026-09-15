<?php

namespace App\Listeners;

use App\Models\GroupMember;
use Illuminate\Auth\Events\Login;

class MarkGroupInvitesAccepted
{
    /**
     * Clear the invitation-pending state on every group membership for this user.
     */
    public function handle(Login $event): void
    {
        GroupMember::query()
            ->where('user_id', $event->user->getKey())
            ->whereNull('accepted_at')
            ->update(['accepted_at' => now()]);
    }
}

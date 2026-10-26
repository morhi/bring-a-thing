<?php

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;

class SharedNotificationsData extends Data
{
    public function __construct(
        public int $unread,
    ) {}

    /**
     * Count the unread notifications for the bell (F-23); a guest has none.
     */
    public static function forUser(?User $user): self
    {
        return new self(unread: $user?->unreadNotifications()->count() ?? 0);
    }
}

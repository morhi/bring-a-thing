<?php

namespace App\Notifications;

use App\Enums\NotificationKind;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A bell notification of F-23: who receives it, what it points to and how it is phrased.
 */
interface InAppNotification
{
    /**
     * The trigger, which picks the interface text.
     */
    public function kind(): NotificationKind;

    /**
     * The page the notification opens; recipients must be allowed to view it.
     */
    public function subject(): Group|Poll|Event|Roster;

    /**
     * The person whose action caused the notification; they never receive it.
     */
    public function actor(): User;

    /**
     * Everyone the trigger concerns, before the actor and visibility are filtered out.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection;

    /**
     * The text placeholders, stored with the notification.
     *
     * @return array<string, string|null>
     */
    public function params(): array;
}

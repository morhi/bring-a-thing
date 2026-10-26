<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Notifications\InAppNotification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;

class SendInAppNotification
{
    /**
     * Send a bell notification to everyone it concerns (F-23).
     *
     * The actor never hears about their own action, and a recipient who may
     * not view the subject is skipped, so a notification never reveals a
     * poll, event or list that the policies hide (section 8: "No access").
     *
     * @param  InAppNotification&BaseNotification  $notification
     */
    public function handle(InAppNotification $notification): void
    {
        $subject = $notification->subject();

        $recipients = $notification->recipients()
            ->filter()
            ->unique(fn (User $user) => $user->getKey())
            ->reject(fn (User $user) => $user->is($notification->actor()))
            ->filter(fn (User $user) => $user->can('view', $subject))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}

<?php

namespace App\Notifications\Concerns;

use App\Data\NotificationData;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Store a bell notification and push it live on the recipient's private channel (F-23).
 *
 * Only the kind, the subject's id and the params are stored, never a
 * finished text, so the panel renders it in the viewer's current language
 * (F-24). The live payload carries a text in the recipient's own language,
 * because the notification sender switches to their preferred locale.
 */
trait DeliversInApp
{
    /**
     * Deliver to the panel and live, never by email (F-23).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * The stored notification data.
     *
     * @return array{kind: string, subject_type: string, subject_id: int, actor_id: int, params: array<string, string|null>}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => $this->kind()->value,
            'subject_type' => NotificationData::subjectTypeOf($this->subject()),
            'subject_id' => $this->subject()->getKey(),
            'actor_id' => $this->actor()->getKey(),
            'params' => $this->params(),
        ];
    }

    /**
     * The live payload: the same shape the panel lists, so the client can prepend it.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(NotificationData::fromStored(
            id: (string) $this->id,
            data: $this->toDatabase($notifiable),
            createdAt: now(),
            readAt: null,
            subjectSlug: $this->subject()->slug,
            locale: app()->getLocale(),
        )->toArray());
    }

    /**
     * The broadcast event name the client listens for.
     */
    public function broadcastType(): string
    {
        return 'notification.created';
    }

    /**
     * How the actor appears in the text: their name, or their email before they chose one.
     */
    protected function actorName(): string
    {
        return $this->actor()->name ?? (string) $this->actor()->email;
    }
}

<?php

namespace App\Data;

use App\Actions\Auth\SendMagicLink;
use App\Models\Event;
use App\Models\Poll;
use App\Models\User;
use Spatie\LaravelData\Data;

class EntityJoinData extends Data
{
    public function __construct(
        public string $token,
        public string $kind,
        public string $title,
        public ?string $organizer_name,
        public ?string $date,
        public ?string $starts_at,
        public bool $is_authenticated,
        public bool $has_email,
        public int $link_expires_in_minutes,
    ) {}

    /**
     * Build the join page state of an event invite link (F-26).
     *
     * Carries the event's key details only, never the group or its
     * members, since the link grants no access to the group.
     */
    public static function fromEvent(Event $event, ?User $user): self
    {
        return new self(
            token: (string) $event->invite_token,
            kind: 'event',
            title: $event->title,
            organizer_name: $event->organizer?->name,
            date: $event->date->format('Y-m-d'),
            starts_at: EventSummaryData::time($event->starts_at),
            is_authenticated: $user !== null,
            has_email: $user?->email !== null,
            link_expires_in_minutes: SendMagicLink::EXPIRES_IN_MINUTES,
        );
    }

    /**
     * Build the join page state of a poll invite link (F-27).
     *
     * A guest joins with a name only or with an email; is_authenticated
     * turns the form into a one-click join.
     */
    public static function fromPoll(Poll $poll, ?User $user): self
    {
        return new self(
            token: (string) $poll->invite_token,
            kind: 'poll',
            title: $poll->title,
            organizer_name: $poll->organizer?->name,
            date: null,
            starts_at: null,
            is_authenticated: $user !== null,
            has_email: $user?->email !== null,
            link_expires_in_minutes: SendMagicLink::EXPIRES_IN_MINUTES,
        );
    }
}

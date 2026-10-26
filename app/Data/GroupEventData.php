<?php

namespace App\Data;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupEventData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $date,
        public ?string $starts_at,
        public ?string $location,
        public bool $is_past,
        public bool $is_from_poll,
        public int $accepted_count,
        public int $pending_count,
        public ?ParticipationStatus $own_status,
    ) {}

    /**
     * Build an event row of the group page with the counts and the user's own status (F-10, F-11).
     */
    public static function forUser(Event $event, User $user): self
    {
        return new self(
            slug: $event->slug,
            title: $event->title,
            date: $event->date->format('Y-m-d'),
            starts_at: EventSummaryData::time($event->starts_at),
            location: $event->location,
            is_past: $event->isPast(),
            is_from_poll: $event->origin_poll_option_id !== null,
            accepted_count: $event->participations->where('status', ParticipationStatus::Accepted)->count(),
            pending_count: $event->participations->where('status', ParticipationStatus::Pending)->count(),
            own_status: $event->statusOf($user),
        );
    }
}

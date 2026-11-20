<?php

namespace App\Data;

use App\Models\Event;
use App\Support\WallClock;
use Spatie\LaravelData\Data;

class DashboardEventData extends Data
{
    /**
     * @param  array<int, string>  $accepted_names
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $group_slug,
        public ?string $group_name,
        public ?string $date,
        public ?string $starts_at,
        public ?string $location,
        public int $accepted_count,
        public array $accepted_names,
    ) {}

    /**
     * Build an upcoming event the person accepted (F-22).
     *
     * Group slug and name are null for a standalone event (F-29).
     * Expects group and the accepted participations with their users to be
     * loaded; accepted_names holds the first five for the avatar stack.
     */
    public static function fromEvent(Event $event): self
    {
        return new self(
            slug: $event->slug,
            title: $event->title,
            group_slug: $event->group?->slug,
            group_name: $event->group?->name,
            date: $event->date?->format('Y-m-d'),
            starts_at: WallClock::iso($event->starts_at),
            location: $event->location,
            accepted_count: $event->participations->count(),
            accepted_names: $event->participations->take(5)->map(fn ($participation) => (string) $participation->user?->name)->values()->all(),
        );
    }
}

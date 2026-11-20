<?php

namespace App\Data;

use App\Models\Event;
use App\Support\WallClock;
use Spatie\LaravelData\Data;

class EventSummaryData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $date,
        public ?string $starts_at,
        public bool $is_past,
    ) {}

    /**
     * Build the short form of an event used for parent and child event links.
     */
    public static function fromEvent(Event $event): self
    {
        return new self(
            slug: $event->slug,
            title: $event->title,
            date: $event->date?->format('Y-m-d'),
            starts_at: WallClock::iso($event->starts_at),
            is_past: $event->isPast(),
        );
    }
}

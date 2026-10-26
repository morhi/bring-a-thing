<?php

namespace App\Data;

use App\Enums\DashboardWaitingKind;
use App\Models\Event;
use App\Models\Poll;
use Spatie\LaravelData\Data;

class DashboardWaitingData extends Data
{
    public function __construct(
        public DashboardWaitingKind $kind,
        public string $slug,
        public string $title,
        public string $group_slug,
        public string $group_name,
        public ?int $answered,
        public ?int $total,
        public ?string $date,
        public ?string $starts_at,
        public ?int $accepted_count,
        public ?EventSummaryData $source_event,
    ) {}

    /**
     * Build the row of an open poll the person has not answered completely (F-06, F-12).
     *
     * Expects group, event and the person's own responses on the options to be loaded.
     */
    public static function fromPoll(Poll $poll): self
    {
        return new self(
            kind: DashboardWaitingKind::Poll,
            slug: $poll->slug,
            title: $poll->title,
            group_slug: $poll->group->slug,
            group_name: $poll->group->name,
            answered: $poll->options->filter(fn ($option) => $option->responses->isNotEmpty())->count(),
            total: $poll->options->count(),
            date: null,
            starts_at: null,
            accepted_count: null,
            source_event: $poll->event ? EventSummaryData::fromEvent($poll->event) : null,
        );
    }

    /**
     * Build the row of an upcoming event the person has not answered yet (F-11).
     *
     * Expects group and the accepted participations to be loaded.
     */
    public static function fromEvent(Event $event): self
    {
        return new self(
            kind: DashboardWaitingKind::Event,
            slug: $event->slug,
            title: $event->title,
            group_slug: $event->group->slug,
            group_name: $event->group->name,
            answered: null,
            total: null,
            date: $event->date->format('Y-m-d'),
            starts_at: EventSummaryData::time($event->starts_at),
            accepted_count: $event->participations->count(),
            source_event: null,
        );
    }
}

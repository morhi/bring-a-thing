<?php

namespace App\Data;

use App\Enums\PollResponseStatus;
use App\Models\PollOption;
use Spatie\LaravelData\Data;

class PollOptionData extends Data
{
    public function __construct(
        public string $slug,
        public string $date,
        public ?string $starts_at,
        public ?string $ends_at,
        public ?string $label,
        public int $yes_count,
        public int $maybe_count,
        public int $no_count,
        public bool $is_best,
        public bool $is_fixed,
        public bool $has_answers,
        public bool $is_schedule_locked,
        public bool $can_remove,
        public ?string $event_slug,
    ) {}

    /**
     * Build one option column with its totals and lock state (F-06, F-08, F-09).
     *
     * Dates are Y-m-d and times H:i. is_schedule_locked disables the date and
     * time fields in edit mode: the option has answers or is fixed. A fixed
     * option cannot be removed, and a closed poll allows no option changes.
     * event_slug links a fixed option to its event.
     */
    public static function fromOption(PollOption $option, bool $isBest): self
    {
        $option->loadMissing('responses', 'event', 'poll');
        $isFixed = $option->isFixed();

        return new self(
            slug: $option->slug,
            date: $option->date->format('Y-m-d'),
            starts_at: EventSummaryData::time($option->starts_at),
            ends_at: EventSummaryData::time($option->ends_at),
            label: $option->label,
            yes_count: $option->countOf(PollResponseStatus::Yes),
            maybe_count: $option->countOf(PollResponseStatus::Maybe),
            no_count: $option->countOf(PollResponseStatus::No),
            is_best: $isBest,
            is_fixed: $isFixed,
            has_answers: $option->hasAnswers(),
            is_schedule_locked: $option->hasLockedSchedule(),
            can_remove: ! $isFixed && ! $option->poll->isClosed(),
            event_slug: $option->event?->slug,
        );
    }
}

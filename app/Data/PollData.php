<?php

namespace App\Data;

use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Models\Poll;
use App\Models\PollOption;
use App\Support\WallClock;
use Spatie\LaravelData\Data;

class PollData extends Data
{
    /**
     * @param  array<int, PollOptionData>  $options
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public PollType $type,
        public PollGranularity $granularity,
        public bool $uses_time_slots,
        public bool $is_closed,
        public ?string $closed_at,
        public ?string $answer_deadline,
        public bool $auto_close,
        public bool $has_passed_deadline,
        public ?string $group_slug,
        public ?string $group_name,
        public int $organizer_id,
        public ?string $organizer_name,
        public ?EventSummaryData $source_event,
        public array $options,
    ) {}

    /**
     * Build the poll with its options in calendar order (F-06, F-07).
     *
     * granularity "day" is the "by day" mode and "hour" the "time slots"
     * mode; uses_time_slots states the same as a flag. source_event is set
     * for a follow-up poll (F-12). id names the broadcast channels (F-21).
     * group_slug and group_name are null for a standalone poll (F-29).
     * answer_deadline is a UTC instant the page shows in the viewer's zone;
     * has_passed_deadline uses the server clock, so the header agrees with auto-close (F-08).
     */
    public static function fromPoll(Poll $poll): self
    {
        $poll->loadMissing('group', 'organizer', 'event', 'options.responses', 'options.event');
        $poll->options->each(fn (PollOption $option) => $option->setRelation('poll', $poll));
        $bestOptionIds = $poll->bestOptions()->pluck('id');

        return new self(
            id: $poll->id,
            slug: $poll->slug,
            title: $poll->title,
            type: $poll->type,
            granularity: $poll->granularity,
            uses_time_slots: $poll->granularity === PollGranularity::Hour,
            is_closed: $poll->isClosed(),
            closed_at: $poll->closed_at?->toIso8601String(),
            answer_deadline: WallClock::iso($poll->answer_deadline),
            auto_close: $poll->auto_close,
            has_passed_deadline: $poll->hasPassedDeadline(),
            group_slug: $poll->group?->slug,
            group_name: $poll->group?->name,
            organizer_id: $poll->organizer_id,
            organizer_name: $poll->organizer?->name,
            source_event: $poll->event ? EventSummaryData::fromEvent($poll->event) : null,
            options: $poll->options
                ->map(fn (PollOption $option) => PollOptionData::fromOption($option, $bestOptionIds->contains($option->id)))
                ->all(),
        );
    }
}

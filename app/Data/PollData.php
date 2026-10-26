<?php

namespace App\Data;

use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Models\Poll;
use App\Models\PollOption;
use Spatie\LaravelData\Data;

class PollData extends Data
{
    /**
     * @param  array<int, PollOptionData>  $options
     */
    public function __construct(
        public string $slug,
        public string $title,
        public PollType $type,
        public PollGranularity $granularity,
        public bool $uses_time_slots,
        public bool $is_closed,
        public ?string $closed_at,
        public string $group_slug,
        public string $group_name,
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
     * for a follow-up poll (F-12).
     */
    public static function fromPoll(Poll $poll): self
    {
        $poll->loadMissing('group', 'organizer', 'event', 'options.responses', 'options.event');
        $poll->options->each(fn (PollOption $option) => $option->setRelation('poll', $poll));
        $bestOptionIds = $poll->bestOptions()->pluck('id');

        return new self(
            slug: $poll->slug,
            title: $poll->title,
            type: $poll->type,
            granularity: $poll->granularity,
            uses_time_slots: $poll->granularity === PollGranularity::Hour,
            is_closed: $poll->isClosed(),
            closed_at: $poll->closed_at?->toIso8601String(),
            group_slug: $poll->group->slug,
            group_name: $poll->group->name,
            organizer_id: $poll->organizer_id,
            organizer_name: $poll->organizer?->name,
            source_event: $poll->event ? EventSummaryData::fromEvent($poll->event) : null,
            options: $poll->options
                ->map(fn (PollOption $option) => PollOptionData::fromOption($option, $bestOptionIds->contains($option->id)))
                ->all(),
        );
    }
}

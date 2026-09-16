<?php

namespace App\Actions\Polls;

use App\Models\Poll;
use Carbon\CarbonPeriod;

class CreatePollOptions
{
    /**
     * Create the poll's candidate options.
     *
     * Explicit rows are used as given (date-finder polls, and any
     * hour-granularity poll, which takes organizer-defined per-day slots
     * like "lunch"/"dinner"); otherwise a day-granularity attendance poll's
     * date range is expanded into one option per day.
     *
     * @param  array<int, array{date: string, starts_at?: string|null, ends_at?: string|null, label?: string|null}>  $options
     */
    public function handle(Poll $poll, bool $usesExplicitOptions, array $options, ?string $startsOn, ?string $endsOn): void
    {
        if ($usesExplicitOptions) {
            foreach ($options as $option) {
                $poll->options()->create([
                    'date' => $option['date'],
                    'starts_at' => $option['starts_at'] ?? null,
                    'ends_at' => $option['ends_at'] ?? null,
                    'label' => $option['label'] ?? null,
                ]);
            }

            return;
        }

        foreach (CarbonPeriod::create($startsOn, $endsOn) as $date) {
            $poll->options()->create(['date' => $date->toDateString()]);
        }
    }
}

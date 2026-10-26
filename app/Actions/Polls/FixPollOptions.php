<?php

namespace App\Actions\Polls;

use App\Actions\Events\CreateEventFromPollOption;
use App\Models\Event;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FixPollOptions
{
    public function __construct(private CreateEventFromPollOption $createEvent) {}

    /**
     * Fix the selected options of a date finder poll and close it (F-09).
     *
     * Each option becomes an event with participation derived from its
     * responses; the option then counts as fixed through that event. Events
     * of a follow-up poll attach to the source event (F-12).
     *
     * @param  Collection<int, PollOption>  $options
     * @return Collection<int, Event>
     */
    public function handle(Poll $poll, Collection $options): Collection
    {
        return DB::transaction(function () use ($poll, $options) {
            $events = $options->map(fn (PollOption $option) => $this->createEvent->handle($option->setRelation('poll', $poll)));

            $poll->close();

            return $events;
        });
    }
}

<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateEventFromPollOption
{
    /**
     * Create the event for one fixed poll option and derive its participation from the responses (F-09).
     *
     * The event takes the poll's title and the option's date and time. Yes
     * becomes "accepted", maybe becomes "pending", and no or no response
     * leaves the person without a participation status. Nobody is accepted
     * automatically, so a poll without responses yields an event without
     * participants (section 8). Events fixed from a follow-up poll attach
     * to the poll's source event (F-12). Closing the poll and marking the
     * option as fixed stay with the caller.
     */
    public function handle(PollOption $option, ?User $organizer = null): Event
    {
        $poll = $option->poll;

        return DB::transaction(function () use ($option, $poll, $organizer) {
            $event = Event::query()->make([
                'title' => $poll->title,
                'date' => $option->date->format('Y-m-d'),
                'starts_at' => $option->starts_at,
                'end_date' => $option->ends_at === null ? null : $option->date->format('Y-m-d'),
                'ends_at' => $option->ends_at,
            ]);
            $event->group_id = $poll->group_id;
            $event->organizer_id = $organizer?->getKey() ?? $poll->organizer_id;
            $event->origin_poll_option_id = $option->getKey();
            $event->parent_event_id = $poll->event_id;
            $event->save();

            $rows = $option->responses()
                ->whereIn('status', [PollResponseStatus::Yes, PollResponseStatus::Maybe])
                ->get()
                ->map(fn (PollResponse $response) => [
                    'user_id' => $response->user_id,
                    'status' => $response->status === PollResponseStatus::Yes ? ParticipationStatus::Accepted : ParticipationStatus::Pending,
                ]);

            $event->participations()->createMany($rows->all());

            return $event;
        });
    }
}

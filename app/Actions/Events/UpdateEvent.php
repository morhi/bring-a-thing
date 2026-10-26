<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class UpdateEvent
{
    /**
     * Update the event's details and reset acceptances when its start moves (F-10).
     *
     * An acceptance applied to the old date or time, so every "accepted"
     * becomes "pending" again, the organizer's included. Declines stay, and
     * claims are never touched (section 8: "Event moved").
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Event $event, array $attributes): Event
    {
        return DB::transaction(function () use ($event, $attributes) {
            $event->fill($attributes);
            $startMoved = $event->isDirty(['date', 'starts_at']);
            $event->save();

            if ($startMoved) {
                $event->participations()
                    ->where('status', ParticipationStatus::Accepted)
                    ->update(['status' => ParticipationStatus::Pending]);
            }

            return $event;
        });
    }
}

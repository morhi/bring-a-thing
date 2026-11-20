<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class UpdateEvent
{
    /**
     * Update the event's details; a real move of its start resets acceptances when asked to (F-10).
     *
     * An acceptance applied to the old date or time, so a reset turns every
     * "accepted" into "pending", the organizer's included. Declines stay, and
     * claims are never touched (section 8: "Event moved"). Returns whether
     * the date or start time really changed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Event $event, array $attributes, bool $resetAcceptances): bool
    {
        return DB::transaction(function () use ($event, $attributes, $resetAcceptances) {
            $moved = $this->hasMoved($event, $attributes);
            $event->fill($attributes);
            $event->save();

            if ($moved && $resetAcceptances) {
                $event->participations()
                    ->where('status', ParticipationStatus::Accepted)
                    ->update(['status' => ParticipationStatus::Pending]);
            }

            return $moved;
        });
    }

    /**
     * Whether the attributes change the event's date or start time.
     *
     * Compares normalized values (Y-m-d, H:i): MySQL returns times with
     * seconds ("18:00:00") while the form sends "18:00", which isDirty()
     * would count as a change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function hasMoved(Event $event, array $attributes): bool
    {
        if (array_key_exists('date', $attributes) && $event->date->format('Y-m-d') !== substr((string) $attributes['date'], 0, 10)) {
            return true;
        }

        if (! array_key_exists('starts_at', $attributes)) {
            return false;
        }

        return $this->time($event->starts_at) !== $this->time($attributes['starts_at']);
    }

    private function time(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 5);
    }
}

<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateEvent
{
    /**
     * Update the event's details; a real move of its start resets acceptances when asked to (F-10).
     *
     * Times arrive as UTC instants (section 9).
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
     * Whether the attributes change the event's start date or start instant.
     *
     * Compares a plain date as Y-m-d and an instant in UTC to the minute,
     * since isDirty() would count a re-entered identical time as a change.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function hasMoved(Event $event, array $attributes): bool
    {
        if (array_key_exists('date', $attributes) && $event->date?->format('Y-m-d') !== $this->day($attributes['date'])) {
            return true;
        }

        if (! array_key_exists('starts_at', $attributes)) {
            return false;
        }

        return $this->instant($event->starts_at) !== $this->instant($attributes['starts_at']);
    }

    private function day(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : substr((string) $value, 0, 10);
    }

    private function instant(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Carbon::parse($value)->utc()->format('Y-m-d H:i');
    }
}

<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    /**
     * Create an event directly in the group, or standalone, and ask every member to accept or decline (F-10).
     *
     * Every member starts as "pending"; the organizer is "accepted", because
     * creating the event is their own commitment to the date. A sub-event
     * (F-10, F-12) instead starts with the parent's current participants,
     * "accepted" and "pending", as "pending". A standalone event (F-29)
     * has no members to ask: it starts with the organizer alone, and a
     * standalone sub-event with the parent's participants.
     *
     * @param  array{title: string, description?: string|null, location?: string|null, date: string|null, starts_at?: Carbon|null, end_date?: string|null, ends_at?: Carbon|null}  $attributes  times as UTC instants (section 9)
     */
    public function handle(?Group $group, User $organizer, array $attributes, ?Event $parent = null): Event
    {
        return DB::transaction(function () use ($group, $organizer, $attributes, $parent) {
            $event = Event::query()->make($attributes);
            $event->group_id = $group?->getKey();
            $event->organizer_id = $organizer->getKey();
            $event->parent_event_id = $parent?->getKey();
            $event->save();

            $memberIds = $group?->members()->pluck('users.id') ?? collect([$organizer->getKey()]);

            if ($parent !== null) {
                $participantIds = $parent->participations()
                    ->whereIn('status', [ParticipationStatus::Accepted, ParticipationStatus::Pending])
                    ->pluck('user_id');

                $memberIds = $group === null ? $participantIds : $memberIds->intersect($participantIds);
                $memberIds = $memberIds->push($organizer->getKey())->unique();
            }

            $rows = $memberIds->values()->map(fn (int $userId) => [
                'user_id' => $userId,
                'status' => $userId === $organizer->getKey() ? ParticipationStatus::Accepted : ParticipationStatus::Pending,
            ]);

            $event->participations()->createMany($rows->all());

            return $event;
        });
    }
}

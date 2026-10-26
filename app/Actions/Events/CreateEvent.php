<?php

namespace App\Actions\Events;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    /**
     * Create an event directly in the group and ask every member to accept or decline (F-10).
     *
     * Every member starts as "pending"; the organizer is "accepted", because
     * creating the event is their own commitment to the date.
     *
     * @param  array{title: string, description?: string|null, location?: string|null, date: string, starts_at?: string|null, end_date?: string|null, ends_at?: string|null}  $attributes
     */
    public function handle(Group $group, User $organizer, array $attributes): Event
    {
        return DB::transaction(function () use ($group, $organizer, $attributes) {
            $event = Event::query()->make($attributes);
            $event->group_id = $group->getKey();
            $event->organizer_id = $organizer->getKey();
            $event->save();

            $rows = $group->members()->pluck('users.id')->map(fn (int $userId) => [
                'user_id' => $userId,
                'status' => $userId === $organizer->getKey() ? ParticipationStatus::Accepted : ParticipationStatus::Pending,
            ]);

            $event->participations()->createMany($rows->all());

            return $event;
        });
    }
}

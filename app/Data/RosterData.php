<?php

namespace App\Data;

use App\Enums\RosterPlacement;
use App\Models\Roster;
use App\Models\User;
use Spatie\LaravelData\Data;

class RosterData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public ?string $description,
        public ?string $date,
        public bool $is_past,
        public RosterPlacement $placement,
        public ?RosterParentData $group,
        public ?RosterParentData $event,
        public int $owner_id,
        public ?string $owner_name,
        public bool $members_can_add_items,
        public ?int $attendance_poll_option_id,
        public ?AttendanceDayData $attendance_day,
        public ?string $share_url,
    ) {}

    /**
     * Build the key details of a list (F-13).
     *
     * A list is standalone, belongs to a group or belongs to an event. The
     * share link only reaches whoever manages the list (F-19). The id names
     * the list's broadcast channel (F-21).
     */
    public static function forUser(Roster $roster, User $user): self
    {
        $roster->loadMissing('owner', 'group', 'event', 'attendancePollOption');

        return new self(
            id: $roster->id,
            slug: $roster->slug,
            title: $roster->title,
            description: $roster->description,
            date: $roster->date?->format('Y-m-d'),
            is_past: $roster->isPast(),
            placement: self::placementOf($roster),
            group: $roster->group ? new RosterParentData($roster->group->id, $roster->group->slug, $roster->group->name) : null,
            event: $roster->event ? new RosterParentData($roster->event->id, $roster->event->slug, $roster->event->title) : null,
            owner_id: $roster->owner_id,
            owner_name: $roster->owner?->name,
            members_can_add_items: $roster->members_can_add_items,
            attendance_poll_option_id: $roster->attendance_poll_option_id,
            attendance_day: $roster->attendancePollOption ? AttendanceDayData::fromOption($roster->attendancePollOption, $user, $roster->isManagedBy($user)) : null,
            share_url: $roster->share_token !== null && $roster->isManagedBy($user)
                ? route('shared-rosters.show', $roster->share_token)
                : null,
        );
    }

    /**
     * Where the list lives: standalone, in a group or in an event (F-13).
     */
    public static function placementOf(Roster $roster): RosterPlacement
    {
        if ($roster->event_id !== null) {
            return RosterPlacement::Event;
        }

        if ($roster->group_id !== null) {
            return RosterPlacement::Group;
        }

        return RosterPlacement::Standalone;
    }
}

<?php

namespace App\Data;

use App\Models\CustomField;
use App\Models\Event;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;
use Spatie\LaravelData\Data;

class RosterFormData extends Data
{
    /**
     * @param  array<int, RosterParentData>  $groups
     * @param  array<int, CustomFieldData>  $custom_fields
     * @param  array<int, AttendanceOptionData>  $attendance_options
     */
    public function __construct(
        public ?RosterData $roster,
        public array $groups,
        public ?RosterParentData $group,
        public ?RosterParentData $event,
        public array $custom_fields,
        public array $attendance_options,
        public bool $can_delete,
        public bool $can_duplicate,
        public bool $can_manage_fields,
        public bool $can_share,
    ) {}

    /**
     * Build the "New list" page, preselecting the group or event it was opened from (F-13, F-14).
     *
     * groups offers every group of the user as placement; an event list
     * is only created from its event, so event is set or null.
     */
    public static function forCreate(User $user, ?Group $group = null, ?Event $event = null): self
    {
        return new self(
            roster: null,
            groups: $user->groups()->orderBy('name')->get()
                ->map(fn (Group $userGroup) => new RosterParentData($userGroup->id, $userGroup->slug, $userGroup->name))
                ->all(),
            group: $group ? new RosterParentData($group->id, $group->slug, $group->name) : null,
            event: $event ? new RosterParentData($event->id, $event->slug, $event->title) : null,
            custom_fields: [],
            attendance_options: [],
            can_delete: false,
            can_duplicate: false,
            can_manage_fields: false,
            can_share: false,
        );
    }

    /**
     * Build the "New list" page in edit mode (F-14, F-16 to F-19).
     *
     * The placement stays as it is; attendance days come only from the
     * attendance polls of the list's own group that are open to the user
     * (F-12, F-18). Custom fields belong to the list owner (F-16).
     */
    public static function forEdit(Roster $roster, User $user): self
    {
        $roster->loadMissing('customFields');
        $data = RosterData::forUser($roster, $user);

        return new self(
            roster: $data,
            groups: [],
            group: $data->group,
            event: $data->event,
            custom_fields: $roster->customFields->map(fn (CustomField $field) => CustomFieldData::fromField($field))->all(),
            attendance_options: AttendanceOptionData::forGroup($roster->group, $user),
            can_delete: $user->can('delete', $roster),
            can_duplicate: $user->can('duplicate', $roster),
            can_manage_fields: $user->can('create', [CustomField::class, $roster]),
            can_share: $user->can('update', $roster),
        );
    }
}

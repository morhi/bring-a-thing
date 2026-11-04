<?php

namespace App\Data;

use App\Models\Event;
use App\Models\Roster;
use App\Models\User;
use Spatie\LaravelData\Data;

class EventPermissionsData extends Data
{
    public function __construct(
        public bool $can_edit,
        public bool $can_delete,
        public bool $can_respond,
        public bool $can_set_status_for_others,
        public bool $can_remove_participants,
        public bool $can_create_follow_up_poll,
        public bool $can_create_sub_event,
        public bool $can_create_list,
        public bool $can_comment,
        public bool $can_manage_invite_link,
        public bool $can_view_group,
    ) {}

    /**
     * Resolve which event actions the user may see (F-10, F-11, F-12).
     *
     * can_set_status_for_others decides whether "Manage" appears on the
     * participation block. Follow-up polls are for accepted participants
     * and managers, and only on events without a parent event. can_comment
     * shows the comment thread to participants and managers (F-20).
     * can_create_list follows the list policy: participants and managers (F-13).
     * can_manage_invite_link shows the invite link card to managers (F-26).
     * can_view_group is false for guests who reach the event through a
     * grant, so the page hides links to the group (F-26, F-27).
     */
    public static function forUser(Event $event, User $user): self
    {
        return new self(
            can_edit: $user->can('update', $event),
            can_delete: $user->can('delete', $event),
            can_respond: $user->can('respond', $event),
            can_set_status_for_others: $user->can('setStatusForOthers', $event),
            can_remove_participants: $user->can('removeParticipant', $event),
            can_create_follow_up_poll: $user->can('createFollowUpPoll', $event),
            can_create_sub_event: $user->can('createSubEvent', $event),
            can_create_list: $user->can('createForEvent', [Roster::class, $event]),
            can_comment: $user->can('comment', $event),
            can_manage_invite_link: $user->can('manageInviteLink', $event),
            can_view_group: $user->can('view', $event->group),
        );
    }
}

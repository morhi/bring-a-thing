<?php

namespace App\Data;

use App\Models\Poll;
use App\Models\User;
use Spatie\LaravelData\Data;

class PollPermissionsData extends Data
{
    public function __construct(
        public bool $can_edit,
        public bool $can_delete,
        public bool $can_respond,
        public bool $can_fix,
        public bool $can_close,
        public bool $can_reopen,
        public bool $can_manage_invite_link,
        public bool $can_view_group,
    ) {}

    /**
     * Resolve which poll actions the user may see (F-06 to F-09).
     *
     * Attendance polls never offer fixing (F-07). A date finder poll closes
     * by fixing dates; plain closing is only offered once it has a fixed
     * option, for example after reopening. can_manage_invite_link shows
     * the invite link card to editors (F-27); can_view_group is false for
     * guests who reach the poll through a grant (F-26, F-27).
     */
    public static function forUser(Poll $poll, User $user): self
    {
        $canEdit = $user->can('update', $poll);
        $isOpen = ! $poll->isClosed();

        return new self(
            can_edit: $canEdit,
            can_delete: $user->can('delete', $poll),
            can_respond: $isOpen && $user->can('respond', $poll),
            can_fix: $isOpen && $user->can('fix', $poll),
            can_close: $isOpen && $canEdit && $poll->canCloseWithoutFixing(),
            can_reopen: ! $isOpen && $canEdit,
            can_manage_invite_link: $user->can('manageInviteLink', $poll),
            can_view_group: $user->can('view', $poll->group),
        );
    }
}

<?php

namespace App\Actions\Groups;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Actions\Friends\AddFriend;
use App\Actions\Notifications\SendInAppNotification;
use App\Enums\GroupJoinMethod;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use App\Notifications\GroupInvitation;

class InviteMemberToGroup
{
    public function __construct(
        private readonly FindOrCreateUserByEmail $findOrCreateUser,
        private readonly SendMagicLink $sendMagicLink,
        private readonly AddFriend $addFriend,
        private readonly SendInAppNotification $sendNotification,
    ) {}

    /**
     * Find or create a user by email, add them to the group as a member, and send a magic link.
     *
     * Reuses the same shadow-account path as self-registration, so inviting
     * the same email twice reuses the existing user and does not duplicate membership.
     * The name is only applied when a new shadow account is created; an
     * existing user's own name is never overwritten. Also saves the invitee
     * as a friend of the inviter, for easier re-referencing next time. A new
     * member hears about the invitation in the bell (F-23).
     */
    public function handle(Group $group, string $email, ?string $name, User $inviter): User
    {
        $user = $this->findOrCreateUser->handle($email, $name);

        if (! $group->hasMember($user)) {
            $group->addMember($user, GroupRole::Member, joinedVia: GroupJoinMethod::Invite);
            $this->sendNotification->handle(new GroupInvitation($group, $inviter, $user));
        }

        $this->sendMagicLink->handle($user);

        if ($user->isNot($inviter)) {
            $this->addFriend->handle($inviter, $email, $name);
        }

        return $user;
    }
}

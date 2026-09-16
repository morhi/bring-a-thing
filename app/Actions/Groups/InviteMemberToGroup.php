<?php

namespace App\Actions\Groups;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Actions\Friends\AddFriend;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

class InviteMemberToGroup
{
    public function __construct(
        private readonly FindOrCreateUserByEmail $findOrCreateUser,
        private readonly SendMagicLink $sendMagicLink,
        private readonly AddFriend $addFriend,
    ) {}

    /**
     * Find or create a user by email, add them to the group as a member, and send a magic link.
     *
     * Reuses the same shadow-account path as self-registration, so inviting
     * the same email twice reuses the existing user and does not duplicate membership.
     * The name is only applied when a new shadow account is created; an
     * existing user's own name is never overwritten. Also saves the invitee
     * as a friend of the inviter, for easier re-referencing next time.
     */
    public function handle(Group $group, string $email, string $name, User $inviter): User
    {
        $user = $this->findOrCreateUser->handle($email, $name);

        if (! $group->members()->whereKey($user->getKey())->exists()) {
            $group->addMember($user, GroupRole::Member);
        }

        $this->sendMagicLink->handle($user);

        if ($user->isNot($inviter)) {
            $this->addFriend->handle($inviter, $email, $name);
        }

        return $user;
    }
}

<?php

namespace App\Actions\Groups;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;

class InviteMemberToGroup
{
    public function __construct(
        private readonly FindOrCreateUserByEmail $findOrCreateUser,
        private readonly SendMagicLink $sendMagicLink,
    ) {}

    /**
     * Find or create a user by email, add them to the group as a member, and send a magic link.
     *
     * Reuses the same shadow-account path as self-registration, so inviting
     * the same email twice reuses the existing user and does not duplicate membership.
     * The name is only applied when a new shadow account is created; an
     * existing user's own name is never overwritten.
     */
    public function handle(Group $group, string $email, string $name): User
    {
        $user = $this->findOrCreateUser->handle($email, $name);

        if (! $group->members()->whereKey($user->getKey())->exists()) {
            $group->addMember($user, GroupRole::Member);
        }

        $this->sendMagicLink->handle($user);

        return $user;
    }
}

<?php

namespace App\Data;

use App\Actions\Auth\SendMagicLink;
use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class GroupJoinData extends Data
{
    /**
     * @param  array<int, GroupJoinMemberData>  $members
     */
    public function __construct(
        public string $token,
        public string $group_name,
        public bool $locked,
        public int $member_count,
        public array $members,
        public bool $is_authenticated,
        public bool $needs_email,
        public bool $has_email,
        public int $link_expires_in_minutes,
    ) {}

    /**
     * Build the join page state for an invite link (F-04).
     *
     * needs_email is true when joining requires an email login first: a
     * guest on a closed link, or a logged-in account without a verified email.
     * has_email tells a logged-in account whether to add an email or to log
     * in through a login link. members carries names only, for the avatar
     * stack, never email addresses.
     */
    public static function fromGroup(Group $group, ?User $user): self
    {
        $members = $group->members()->orderByPivot('created_at')->limit(5)->get(['users.id', 'users.name']);

        return new self(
            token: (string) $group->invite_token,
            group_name: $group->name,
            locked: $group->invite_link_locked,
            member_count: $group->members()->count(),
            members: $members->map(fn (User $member) => new GroupJoinMemberData($member->id, $member->name))->all(),
            is_authenticated: $user !== null,
            needs_email: $group->invite_link_locked && ! $user?->hasVerifiedEmail(),
            has_email: $user?->email !== null,
            link_expires_in_minutes: SendMagicLink::EXPIRES_IN_MINUTES,
        );
    }
}

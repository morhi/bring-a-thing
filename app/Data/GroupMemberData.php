<?php

namespace App\Data;

use App\Enums\GroupRole;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class GroupMemberData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public GroupRole $role,
        public bool $is_pending,
        public bool $joined_via_link,
    ) {}

    /**
     * Build a member row from a user loaded through the group's members relation (F-03, F-04).
     *
     * is_pending drives the "Invitation pending" label, joined_via_link the "Joined via link" label.
     */
    public static function fromMember(User $member): self
    {
        /** @var GroupMember $membership */
        $membership = $member->pivot;

        return new self(
            id: $member->id,
            name: $member->name,
            email: $member->email,
            role: $membership->role,
            is_pending: $membership->isPending(),
            joined_via_link: $membership->joinedViaLink(),
        );
    }

    /**
     * Build the member list ordered by role (owner, admins, members), then by name.
     *
     * Members without a name sort last within their role.
     *
     * @param  Collection<int, User>  $members
     * @return array<int, self>
     */
    public static function listOf(Collection $members): array
    {
        $order = [GroupRole::Owner->value => 0, GroupRole::Admin->value => 1, GroupRole::Member->value => 2];

        return $members
            ->map(fn (User $member) => self::fromMember($member))
            ->sort(fn (self $a, self $b) => [$order[$a->role->value], $a->name === null, mb_strtolower((string) ($a->name ?? $a->email))]
                <=> [$order[$b->role->value], $b->name === null, mb_strtolower((string) ($b->name ?? $b->email))])
            ->values()
            ->all();
    }
}

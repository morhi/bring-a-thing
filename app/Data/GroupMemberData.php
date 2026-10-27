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
     * The email stays out when the viewer may not see it (section 4, privacy).
     */
    public static function fromMember(User $member, bool $withEmail = true): self
    {
        /** @var GroupMember $membership */
        $membership = $member->pivot;

        return new self(
            id: $member->id,
            name: $member->name,
            email: $withEmail ? $member->email : null,
            role: $membership->role,
            is_pending: $membership->isPending(),
            joined_via_link: $membership->joinedViaLink(),
        );
    }

    /**
     * Build the member list ordered by role (owner, admins, members), then by name.
     *
     * Members without a name sort last within their role. With a viewer,
     * only owners and admins see every email; anyone else sees their own
     * email only, so members cannot collect each other's addresses.
     *
     * @param  Collection<int, User>  $members
     * @return array<int, self>
     */
    public static function listOf(Collection $members, ?User $viewer = null): array
    {
        $order = [GroupRole::Owner->value => 0, GroupRole::Admin->value => 1, GroupRole::Member->value => 2];
        $roleOf = fn (User $member): GroupRole => $member->pivot->role;
        $viewerRole = $viewer ? $members->firstWhere('id', $viewer->getKey())?->pivot->role : null;
        $seesAllEmails = $viewer === null || in_array($viewerRole, [GroupRole::Owner, GroupRole::Admin], true);

        return $members
            ->sort(fn (User $a, User $b) => [$order[$roleOf($a)->value], $a->name === null, mb_strtolower((string) ($a->name ?? $a->email))]
                <=> [$order[$roleOf($b)->value], $b->name === null, mb_strtolower((string) ($b->name ?? $b->email))])
            ->map(fn (User $member) => self::fromMember($member, $seesAllEmails || $member->is($viewer)))
            ->values()
            ->all();
    }
}

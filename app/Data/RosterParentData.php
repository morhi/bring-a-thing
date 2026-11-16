<?php

namespace App\Data;

use App\Models\Group;
use App\Models\User;
use Spatie\LaravelData\Data;

class RosterParentData extends Data
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {}

    /**
     * The user's own groups by name, as offered by the create pages' group select.
     *
     * @return array<int, self>
     */
    public static function forGroups(User $user): array
    {
        return $user->groups()->orderBy('name')->get()
            ->map(fn (Group $group) => new self($group->id, $group->slug, $group->name))
            ->all();
    }
}

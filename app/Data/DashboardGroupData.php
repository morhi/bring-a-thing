<?php

namespace App\Data;

use App\Enums\GroupRole;
use App\Models\Group;
use Spatie\LaravelData\Data;

class DashboardGroupData extends Data
{
    public function __construct(
        public string $slug,
        public string $name,
        public GroupRole $role,
        public int $member_count,
        public int $open_poll_count,
        public int $upcoming_event_count,
    ) {}

    /**
     * Build a group card (F-22).
     *
     * Expects the membership pivot and the members_count, open_polls_count
     * and upcoming_events_count aggregates; open polls only count those the
     * person may see.
     */
    public static function fromGroup(Group $group): self
    {
        return new self(
            slug: $group->slug,
            name: $group->name,
            role: $group->pivot->role,
            member_count: $group->members_count,
            open_poll_count: $group->open_polls_count,
            upcoming_event_count: $group->upcoming_events_count,
        );
    }
}

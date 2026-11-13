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
        public bool $is_guest,
        public ?GroupRole $role,
        public ?int $member_count,
        public int $open_poll_count,
        public int $upcoming_event_count,
        public int $active_list_count,
    ) {}

    /**
     * Build the card of a group the person is a member of (F-22).
     *
     * Expects the membership pivot and the members_count, open_polls_count
     * and upcoming_events_count aggregates; open polls only count those the
     * person may see. The list count covers group and event lists alike.
     */
    public static function fromGroup(Group $group, int $activeListCount): self
    {
        return new self(
            slug: $group->slug,
            name: $group->name,
            is_guest: false,
            role: $group->pivot->role,
            member_count: $group->members_count,
            open_poll_count: $group->open_polls_count,
            upcoming_event_count: $group->upcoming_events_count,
            active_list_count: $activeListCount,
        );
    }

    /**
     * Build the card of a guest group, reached only through invite links (F-22, F-26, F-27).
     *
     * Expects open_polls_count and upcoming_events_count limited to items
     * the person reaches; role and member count stay hidden from a guest.
     */
    public static function forGuest(Group $group, int $activeListCount): self
    {
        return new self(
            slug: $group->slug,
            name: $group->name,
            is_guest: true,
            role: null,
            member_count: null,
            open_poll_count: $group->open_polls_count,
            upcoming_event_count: $group->upcoming_events_count,
            active_list_count: $activeListCount,
        );
    }
}

<?php

namespace App\Data;

use App\Models\Roster;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\Data;

class WithoutGroupData extends Data
{
    /**
     * @param  array<int, DashboardListData>  $lists
     */
    public function __construct(
        public array $lists,
    ) {}

    /**
     * Build the virtual group "Without groups" of a person (F-29).
     *
     * Past lists stay on the page, since it is the only way back to them
     * outside a group. Items reached through invite links belong to their
     * real group and show there as a guest group (F-22, F-26, F-27).
     */
    public static function forUser(User $user): self
    {
        $lists = DashboardListData::ordered(self::lists($user)->with(DashboardListData::RELATIONS))
            ->get()
            ->map(fn (Roster $roster) => DashboardListData::fromRoster($roster))
            ->all();

        return new self(lists: $lists);
    }

    /**
     * The counts for the dashboard card, or null without an own groupless list (F-22, F-29).
     */
    public static function countsFor(User $user): ?DashboardWithoutGroupData
    {
        $count = self::lists($user)->count();

        if ($count === 0) {
            return null;
        }

        return new DashboardWithoutGroupData(lists_count: $count);
    }

    /**
     * The person's own lists without group and event (F-13, F-29).
     *
     * @return Builder<Roster>
     */
    private static function lists(User $user): Builder
    {
        return Roster::query()
            ->whereNull('group_id')
            ->whereNull('event_id')
            ->where('owner_id', $user->getKey());
    }
}

<?php

namespace App\Data;

use App\Models\Roster;
use App\Models\User;
use Spatie\LaravelData\Data;

class ListsIndexData extends Data
{
    /**
     * @param  array<int, DashboardListData>  $lists
     */
    public function __construct(
        public array $lists,
        public ListsFiltersData $filters,
    ) {}

    /**
     * Build the page "All lists" with the lists the person may open, narrowed by the filters (F-28).
     *
     * "Tasks only" keeps lists with at least one item without a quantity,
     * which is what a task is (F-14). Without "all", past lists and lists
     * of past events are left out.
     */
    public static function forUser(User $user, ListsFiltersData $filters): self
    {
        $query = Roster::query()
            ->visibleTo($user)
            ->with(DashboardListData::RELATIONS)
            ->when($filters->mine, fn ($query) => $query->ownOrClaimedBy($user))
            ->when($filters->tasks, fn ($query) => $query->withTasks())
            ->when(! $filters->all, fn ($query) => $query->active());

        return new self(
            lists: DashboardListData::ordered($query)->get()->map(fn (Roster $roster) => DashboardListData::fromRoster($roster))->all(),
            filters: $filters,
        );
    }
}

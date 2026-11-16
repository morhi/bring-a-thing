<?php

namespace App\Data;

use App\Enums\DashboardPollStatus;
use App\Models\Poll;
use App\Models\User;
use Spatie\LaravelData\Data;

class PollsIndexData extends Data
{
    /**
     * @param  array<int, DashboardPollData>  $polls
     */
    public function __construct(
        public array $polls,
        public PollsFiltersData $filters,
    ) {}

    /**
     * Build the page "All polls" with the polls the person may open, narrowed by the filters (F-31).
     *
     * Visibility follows the dashboard (F-22): withdrawn polls and follow-up
     * polls are left out. "Unanswered" keeps polls the person answered not at
     * all or only in part. Without "all", closed polls are left out; open
     * polls come first.
     */
    public static function forUser(User $user, PollsFiltersData $filters): self
    {
        $polls = Poll::query()
            ->visibleTo($user)
            ->whereNull('event_id')
            ->when($filters->mine, fn ($query) => $query->where('organizer_id', $user->getKey()))
            ->when(! $filters->all, fn ($query) => $query->whereNull('closed_at'))
            ->with([
                'group',
                'options.responses' => fn ($query) => $query->where('user_id', $user->getKey()),
            ])
            ->orderByRaw('closed_at is not null')
            ->orderBy('id')
            ->get()
            ->map(fn (Poll $poll) => DashboardPollData::fromPoll($poll))
            ->when($filters->unanswered, fn ($polls) => $polls->filter(fn (DashboardPollData $poll) => $poll->status !== DashboardPollStatus::Answered));

        return new self(
            polls: $polls->values()->all(),
            filters: $filters,
        );
    }
}

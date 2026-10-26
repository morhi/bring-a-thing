<?php

namespace App\Data;

use App\Enums\PollResponseStatus;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\User;
use Spatie\LaravelData\Data;

class AttendanceDayData extends Data
{
    /**
     * @param  array<int, int>  $available_user_ids
     * @param  array<int, int>  $unavailable_user_ids
     */
    public function __construct(
        public int $option_id,
        public string $poll_slug,
        public string $poll_title,
        public string $date,
        public ?string $starts_at,
        public ?string $label,
        public array $available_user_ids,
        public array $unavailable_user_ids,
    ) {}

    /**
     * Build the linked attendance day of a list or item (F-18).
     *
     * available_user_ids holds everyone who answered yes or maybe; anyone
     * else answered no or nothing and gets the "Claim anyway" warning,
     * also when someone claims for them. unavailable_user_ids holds the
     * explicit no answers, so the warning can name which of the two it is.
     *
     * Other people's answers only reach viewers who claim for others on the
     * list and may see the poll themselves; everyone else gets just their own
     * answer, which keeps the field names stable for the claim warning.
     */
    public static function fromOption(PollOption $option, User $viewer, bool $viewerManagesClaims): self
    {
        $option->loadMissing('poll', 'responses');
        $responses = $viewerManagesClaims && $option->poll->isOpenTo($viewer)
            ? $option->responses
            : $option->responses->where('user_id', $viewer->getKey());

        return new self(
            option_id: $option->id,
            poll_slug: $option->poll->slug,
            poll_title: $option->poll->title,
            date: $option->date->format('Y-m-d'),
            starts_at: EventSummaryData::time($option->starts_at),
            label: $option->label,
            available_user_ids: $responses
                ->filter(fn (PollResponse $response) => $response->status !== PollResponseStatus::No)
                ->pluck('user_id')
                ->values()
                ->all(),
            unavailable_user_ids: $responses
                ->filter(fn (PollResponse $response) => $response->status === PollResponseStatus::No)
                ->pluck('user_id')
                ->values()
                ->all(),
        );
    }

    /**
     * Whether claiming on this day needs the "Claim anyway" confirmation for the given person (F-18).
     */
    public function warns(User $user): bool
    {
        return ! in_array($user->getKey(), $this->available_user_ids, true);
    }
}

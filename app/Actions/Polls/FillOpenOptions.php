<?php

namespace App\Actions\Polls;

use App\Enums\PollResponseStatus;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FillOpenOptions
{
    /**
     * Answer every option the person has not answered yet with the given status (F-06).
     *
     * Existing answers stay, so the person can mark the exceptions first and fill the rest afterwards.
     *
     * @return list<int> Ids of the options that were answered
     */
    public function handle(Poll $poll, User $person, PollResponseStatus $status): array
    {
        return DB::transaction(function () use ($poll, $person, $status) {
            $openOptions = $poll->options()
                ->whereDoesntHave('responses', fn (Builder $query) => $query->where('user_id', $person->getKey()))
                ->lockForUpdate()
                ->get();

            $optionIds = [];

            foreach ($openOptions as $option) {
                $option->responses()->firstOrCreate(
                    ['user_id' => $person->getKey()],
                    ['status' => $status],
                );
                $optionIds[] = $option->id;
            }

            return $optionIds;
        });
    }
}

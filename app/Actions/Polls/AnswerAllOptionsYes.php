<?php

namespace App\Actions\Polls;

use App\Enums\PollResponseStatus;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnswerAllOptionsYes
{
    /**
     * Set the person's answer to yes on every option of the poll (F-06).
     *
     * Existing answers are overwritten, so the person only corrects the exceptions afterwards.
     *
     * @return list<int> Ids of the options that were answered
     */
    public function handle(Poll $poll, User $person): array
    {
        return DB::transaction(function () use ($poll, $person) {
            $optionIds = [];

            foreach ($poll->options()->get() as $option) {
                $option->responses()->updateOrCreate(
                    ['user_id' => $person->getKey()],
                    ['status' => PollResponseStatus::Yes],
                );
                $optionIds[] = $option->id;
            }

            return $optionIds;
        });
    }
}

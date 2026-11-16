<?php

namespace App\Actions\Polls;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawFromPoll
{
    /**
     * Delete the person's responses on the poll and record the withdrawal (F-08).
     *
     * The responses go, so totals and the best option no longer count them;
     * the withdrawal keeps the poll off the person's dashboard (F-22).
     */
    public function handle(Poll $poll, User $person): void
    {
        DB::transaction(function () use ($poll, $person) {
            PollResponse::query()
                ->where('user_id', $person->getKey())
                ->whereIn('poll_option_id', PollOption::query()->where('poll_id', $poll->getKey())->select('id'))
                ->delete();

            $poll->withdrawals()->firstOrCreate(['user_id' => $person->getKey()]);
        });
    }
}

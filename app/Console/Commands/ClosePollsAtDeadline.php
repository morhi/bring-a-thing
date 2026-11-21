<?php

namespace App\Console\Commands;

use App\Actions\Polls\ClosePoll;
use App\Models\Poll;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('polls:close-at-deadline')]
#[Description('Close open polls with auto-close whose answer deadline has passed (F-08)')]
class ClosePollsAtDeadline extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ClosePoll $closePoll): int
    {
        Poll::query()->dueForAutoClose()->each(fn (Poll $poll) => $closePoll->handle($poll));

        return self::SUCCESS;
    }
}

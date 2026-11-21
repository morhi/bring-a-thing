<?php

namespace App\Actions\Polls;

use App\Events\DashboardChanged;
use App\Events\PollChanged;
use App\Models\Poll;

class ClosePoll
{
    /**
     * Close the poll and refresh the open poll pages and dashboards (F-08, F-21).
     *
     * Shared by the manual close and the auto-close at the answer deadline, so both update viewers the same way.
     */
    public function handle(Poll $poll): void
    {
        $poll->close();

        PollChanged::dispatch($poll->id);
        DashboardChanged::forItem($poll);
    }
}

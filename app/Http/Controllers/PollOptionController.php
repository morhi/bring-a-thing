<?php

namespace App\Http\Controllers;

use App\Events\PollChanged;
use App\Http\Requests\Polls\StorePollOptionRequest;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class PollOptionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Add a candidate option to the poll.
     */
    public function store(StorePollOptionRequest $request, Poll $poll): RedirectResponse
    {
        $poll->options()->create($request->safe()->only(['date', 'starts_at', 'ends_at', 'label']));

        PollChanged::dispatch($poll->id);

        return back()->with('success', 'Option added.');
    }

    /**
     * Remove the option and every response on it; fixed options stay (F-08, F-09).
     */
    public function destroy(Poll $poll, PollOption $option): RedirectResponse
    {
        abort_unless($option->poll_id === $poll->getKey(), 404);
        $this->authorize('update', $poll);

        if ($poll->isClosed()) {
            return back()->with('error', 'This poll is closed.');
        }

        if ($option->isFixed()) {
            return back()->with('error', 'A fixed option cannot be removed.');
        }

        $option->delete();

        PollChanged::dispatch($poll->id);

        return back()->with('success', 'Option removed.');
    }
}

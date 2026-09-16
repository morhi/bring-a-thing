<?php

namespace App\Http\Controllers;

use App\Enums\PollResponseStatus;
use App\Http\Requests\Polls\StorePollResponseRequest;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PollResponseController extends Controller
{
    /**
     * Create or update the current user's yes/no/maybe response to the option.
     */
    public function store(StorePollResponseRequest $request, Poll $poll, PollOption $option): RedirectResponse
    {
        if ($option->poll_id !== $poll->getKey()) {
            throw new NotFoundHttpException;
        }

        $option->responses()->updateOrCreate(
            ['user_id' => $request->user()->getKey()],
            ['status' => $request->enum('status', PollResponseStatus::class)],
        );

        return back()->with('success', 'Response saved.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Polls\AnswerAllOptionsYes;
use App\Enums\PollResponseStatus;
use App\Events\DashboardChanged;
use App\Events\PollResponseUpdated;
use App\Http\Requests\Polls\StorePollResponseRequest;
use App\Http\Requests\Polls\StorePollYesToAllRequest;
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

        PollResponseUpdated::dispatch($poll->id, $option->id, $request->user()->id);
        DashboardChanged::dispatch($poll->group_id, [$request->user()->id]);

        return back()->with('success', __('ui.poll.answer.saved'));
    }

    /**
     * Answer yes to every option of the poll for the current user (F-06).
     */
    public function storeYesToAll(StorePollYesToAllRequest $request, Poll $poll, AnswerAllOptionsYes $answerAllOptionsYes): RedirectResponse
    {
        $optionIds = $answerAllOptionsYes->handle($poll, $request->user());

        foreach ($optionIds as $optionId) {
            PollResponseUpdated::dispatch($poll->id, $optionId, $request->user()->id);
        }

        DashboardChanged::dispatch($poll->group_id, [$request->user()->id]);

        return back()->with('success', __('ui.poll.answer.allSaved'));
    }
}

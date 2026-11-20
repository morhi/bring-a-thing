<?php

namespace App\Http\Controllers;

use App\Actions\Polls\FillOpenOptions;
use App\Enums\PollResponseStatus;
use App\Events\DashboardChanged;
use App\Events\PollResponseUpdated;
use App\Http\Requests\Polls\FillOpenPollOptionsRequest;
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

        PollResponseUpdated::dispatch($poll->id, $option->id, $request->user()->id);
        DashboardChanged::dispatch($poll->group_id, [$request->user()->id]);

        return back()->with('success', __('ui.poll.answer.saved'));
    }

    /**
     * Answer every option the current user has not answered yet with one status (F-06).
     */
    public function fillOpen(FillOpenPollOptionsRequest $request, Poll $poll, FillOpenOptions $fillOpenOptions): RedirectResponse
    {
        $status = $request->enum('status', PollResponseStatus::class);
        $optionIds = $fillOpenOptions->handle($poll, $request->user(), $status);

        if ($optionIds === []) {
            return back()->with('error', __('ui.poll.answer.noneOpen'));
        }

        foreach ($optionIds as $optionId) {
            PollResponseUpdated::dispatch($poll->id, $optionId, $request->user()->id);
        }

        DashboardChanged::dispatch($poll->group_id, [$request->user()->id]);

        return back()->with('success', trans_choice('ui.poll.answer.filled', count($optionIds), [
            'n' => count($optionIds),
            'status' => __('ui.poll.answer.'.$status->value),
        ]));
    }
}

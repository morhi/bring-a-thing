<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SendInAppNotification;
use App\Actions\Polls\CreateFollowUpPoll;
use App\Data\PollFormData;
use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Events\EventUpdated;
use App\Http\Requests\Polls\StoreFollowUpPollRequest;
use App\Models\Event;
use App\Notifications\PollCreated;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventFollowUpPollController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the "New follow-up poll" page within the event (F-12).
     */
    public function create(Event $event): Response
    {
        $this->authorize('createFollowUpPoll', $event);

        return Inertia::render('Polls/Create', [
            'form' => PollFormData::forEvent($event),
        ]);
    }

    /**
     * Create a follow-up poll whose audience is the event's accepted participants and managers.
     */
    public function store(StoreFollowUpPollRequest $request, Event $event, CreateFollowUpPoll $createFollowUpPoll, SendInAppNotification $sendNotification): RedirectResponse
    {
        $poll = $createFollowUpPoll->handle(
            $event,
            $request->user(),
            [
                'title' => $request->string('title')->value(),
                'type' => $request->enum('type', PollType::class),
                'granularity' => $request->enum('granularity', PollGranularity::class),
            ],
            $request->usesExplicitOptions(),
            $request->input('options', []),
            $request->input('starts_on'),
            $request->input('ends_on'),
        );

        EventUpdated::dispatch($event->id);

        $sendNotification->handle(new PollCreated($poll, $request->user()));

        return to_route('polls.show', $poll)->with('success', __('ui.newPoll.created'));
    }
}

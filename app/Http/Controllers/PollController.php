<?php

namespace App\Http\Controllers;

use App\Actions\Polls\CreatePollOptions;
use App\Actions\Polls\FixPollOptions;
use App\Actions\Polls\UpdatePoll;
use App\Data\PollFormData;
use App\Data\PollShowData;
use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Events\EventUpdated;
use App\Events\OptionFixed;
use App\Events\PollChanged;
use App\Http\Requests\Polls\ClosePollRequest;
use App\Http\Requests\Polls\FixPollRequest;
use App\Http\Requests\Polls\StorePollRequest;
use App\Http\Requests\Polls\UpdatePollRequest;
use App\Models\Group;
use App\Models\Poll;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PollController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the "New poll" page for the group (F-06, F-07).
     */
    public function create(Group $group): Response
    {
        $this->authorize('createForGroup', [Poll::class, $group]);

        return Inertia::render('Polls/Create', [
            'form' => PollFormData::forGroup($group),
        ]);
    }

    /**
     * Create a new poll on the group, with either explicit option rows or an auto-expanded date range.
     */
    public function store(StorePollRequest $request, Group $group, CreatePollOptions $createOptions): RedirectResponse
    {
        $poll = Poll::query()->make([
            'title' => $request->string('title')->value(),
        ]);
        $poll->type = $request->enum('type', PollType::class);
        $poll->granularity = $request->enum('granularity', PollGranularity::class);
        $poll->organizer_id = $request->user()->getKey();
        $poll->group_id = $group->getKey();
        $poll->save();

        $createOptions->handle(
            $poll,
            $request->usesExplicitOptions(),
            $request->input('options', []),
            $request->input('starts_on'),
            $request->input('ends_on'),
        );

        return to_route('polls.show', $poll)->with('success', 'Poll created.');
    }

    /**
     * Show the poll: options with totals and best option, every respondent's answers and the fixed events.
     */
    public function show(Request $request, Poll $poll): Response
    {
        $this->authorize('view', $poll);

        return Inertia::render('Polls/Show', [
            'page' => PollShowData::forUser($poll, $request->user()),
        ]);
    }

    /**
     * Show the "New poll" page in edit mode to organizer, owner and admins (F-08).
     */
    public function edit(Poll $poll): Response
    {
        $this->authorize('update', $poll);

        return Inertia::render('Polls/Create', [
            'form' => PollFormData::forGroup($poll->group, $poll),
        ]);
    }

    /**
     * Rename the poll and, from the edit page, change and add options.
     */
    public function update(UpdatePollRequest $request, Poll $poll, UpdatePoll $updatePoll): RedirectResponse
    {
        $updatePoll->handle($poll, $request->string('title')->value(), $request->has('options') ? $request->validated('options') : null);

        PollChanged::dispatch($poll->id);

        return to_route('polls.edit', $poll)->with('success', 'Poll updated.');
    }

    /**
     * Close the poll without a selection: attendance polls, or date finder polls that already have a fixed option.
     */
    public function close(ClosePollRequest $request, Poll $poll): RedirectResponse
    {
        $poll->close();

        PollChanged::dispatch($poll->id);

        return to_route('polls.edit', $poll)->with('success', 'Poll closed.');
    }

    /**
     * Fix the selected options as events and close the poll (F-09).
     */
    public function fix(FixPollRequest $request, Poll $poll, FixPollOptions $fixPollOptions): RedirectResponse
    {
        $events = $fixPollOptions->handle($poll, $request->selectedOptions());

        OptionFixed::dispatch($poll->id, $events->pluck('origin_poll_option_id')->all(), $events->pluck('id')->all());

        if ($poll->event_id !== null) {
            EventUpdated::dispatch($poll->event_id);
        }

        return to_route('polls.show', $poll)->with('success', $events->count() === 1 ? 'Date fixed.' : 'Dates fixed.');
    }

    /**
     * Reopen the poll to new responses; events fixed from it stay (F-08).
     */
    public function reopen(Poll $poll): RedirectResponse
    {
        $this->authorize('update', $poll);

        $poll->reopen();

        PollChanged::dispatch($poll->id);

        return to_route('polls.edit', $poll)->with('success', 'Poll reopened.');
    }

    /**
     * Delete the poll, its options, and every response on them.
     */
    public function destroy(Poll $poll): RedirectResponse
    {
        $this->authorize('delete', $poll);

        $group = $poll->group;
        $poll->delete();

        PollChanged::dispatch($poll->id, deleted: true);

        if ($poll->event_id !== null) {
            EventUpdated::dispatch($poll->event_id);
        }

        return to_route('groups.show', $group)->with('success', 'Poll deleted.');
    }
}

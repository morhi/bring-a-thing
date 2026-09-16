<?php

namespace App\Http\Controllers;

use App\Actions\Polls\CreatePollOptions;
use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Http\Requests\Polls\ClosePollRequest;
use App\Http\Requests\Polls\StorePollRequest;
use App\Http\Requests\Polls\UpdatePollRequest;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PollController extends Controller
{
    use AuthorizesRequests;

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
     * Show the poll: options and every member's responses.
     */
    public function show(Request $request, Poll $poll): Response
    {
        $this->authorize('view', $poll);

        $poll->load('group.members', 'organizer', 'chosenOption');
        $poll->load(['options.responses.user']);

        return Inertia::render('Polls/Show', [
            'poll' => $poll,
            'canManage' => $request->user()->can('update', $poll),
            'bestOptionIds' => $poll->bestOptions()->pluck('id'),
        ]);
    }

    /**
     * Show the organizer/admin-only poll settings page: rename, manage options, close/reopen, delete.
     */
    public function edit(Poll $poll): Response
    {
        $this->authorize('update', $poll);

        $poll->load('options.responses', 'group', 'chosenOption');

        return Inertia::render('Polls/Edit', [
            'poll' => $poll,
        ]);
    }

    /**
     * Rename the poll.
     */
    public function update(UpdatePollRequest $request, Poll $poll): RedirectResponse
    {
        $poll->update($request->safe()->only(['title']));

        return to_route('polls.edit', $poll)->with('success', 'Poll updated.');
    }

    /**
     * Close the poll to new votes. A date-finder poll must name the chosen option.
     */
    public function close(ClosePollRequest $request, Poll $poll): RedirectResponse
    {
        $chosenOption = $request->filled('option_id') ? PollOption::findOrFail($request->integer('option_id')) : null;

        $poll->close($chosenOption);

        return to_route('polls.edit', $poll)->with('success', 'Poll closed.');
    }

    /**
     * Reopen the poll to new votes.
     */
    public function reopen(Poll $poll): RedirectResponse
    {
        $this->authorize('update', $poll);

        $poll->reopen();

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

        return to_route('groups.show', $group)->with('success', 'Poll deleted.');
    }
}

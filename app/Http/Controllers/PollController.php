<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SendInAppNotification;
use App\Actions\Polls\CreatePollOptions;
use App\Actions\Polls\FixPollOptions;
use App\Actions\Polls\SendFixedDatesMail;
use App\Actions\Polls\UpdatePoll;
use App\Actions\Polls\WithdrawFromPoll;
use App\Data\PollFormData;
use App\Data\PollShowData;
use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Events\DashboardChanged;
use App\Events\EventUpdated;
use App\Events\OptionFixed;
use App\Events\PollChanged;
use App\Http\Requests\Polls\ClosePollRequest;
use App\Http\Requests\Polls\FixPollRequest;
use App\Http\Requests\Polls\StorePollRequest;
use App\Http\Requests\Polls\UpdatePollRequest;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Notifications\DateFixed;
use App\Notifications\PollCreated;
use App\Support\WallClock;
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
     * Show the "New poll" page with a select over the user's groups, for creating from outside a group (F-06).
     */
    public function createWithoutGroup(Request $request): Response
    {
        return Inertia::render('Polls/Create', [
            'form' => PollFormData::forCreate($request->user()),
        ]);
    }

    /**
     * Create a new poll on the group, with either explicit option rows or an auto-expanded date range.
     */
    public function store(StorePollRequest $request, Group $group, CreatePollOptions $createOptions, SendInAppNotification $sendNotification): RedirectResponse
    {
        return $this->createPoll($request, $group, $createOptions, $sendNotification);
    }

    /**
     * Create a new poll in the group picked by group_id, or standalone without one (F-06, F-29).
     */
    public function storeAny(StorePollRequest $request, CreatePollOptions $createOptions, SendInAppNotification $sendNotification): RedirectResponse
    {
        return $this->createPoll($request, $request->group(), $createOptions, $sendNotification);
    }

    /**
     * Create the poll with its options, refresh the dashboards and notify the audience.
     */
    private function createPoll(StorePollRequest $request, ?Group $group, CreatePollOptions $createOptions, SendInAppNotification $sendNotification): RedirectResponse
    {
        $poll = Poll::query()->make([
            'title' => $request->string('title')->value(),
        ]);
        $poll->type = $request->enum('type', PollType::class);
        $poll->granularity = $request->enum('granularity', PollGranularity::class);
        $poll->organizer_id = $request->user()->getKey();
        $poll->group_id = $group?->getKey();
        $poll->save();

        $createOptions->handle(
            $poll,
            $request->usesExplicitOptions(),
            $request->input('options', []),
            $request->input('starts_on'),
            $request->input('ends_on'),
            WallClock::zoneOf($request->user()),
        );

        DashboardChanged::forItem($poll);

        $sendNotification->handle(new PollCreated($poll, $request->user()));

        return to_route('polls.show', $poll)->with('success', __('ui.newPoll.created'));
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
     * Rename the poll and, from the edit page, change and add options; the edit page returns to the poll.
     */
    public function update(UpdatePollRequest $request, Poll $poll, UpdatePoll $updatePoll): RedirectResponse
    {
        $updatePoll->handle(
            $poll,
            $request->string('title')->value(),
            $request->has('options') ? $request->validated('options') : null,
            $request->validated('remove', []),
            WallClock::zoneOf($request->user()),
        );

        PollChanged::dispatch($poll->id);
        DashboardChanged::forItem($poll);

        return to_route('polls.show', $poll)->with('success', __('ui.poll.settings.saved'));
    }

    /**
     * Close the poll without a selection: attendance polls, or date finder polls that already have a fixed option.
     */
    public function close(ClosePollRequest $request, Poll $poll): RedirectResponse
    {
        $poll->close();

        PollChanged::dispatch($poll->id);
        DashboardChanged::forItem($poll);

        return back(fallback: route('polls.show', $poll))->with('success', __('ui.poll.closed.toast'));
    }

    /**
     * Fix the selected options as events and close the poll (F-09).
     */
    public function fix(FixPollRequest $request, Poll $poll, FixPollOptions $fixPollOptions, SendInAppNotification $sendNotification, SendFixedDatesMail $sendMail): RedirectResponse
    {
        $events = $fixPollOptions->handle($poll, $request->selectedOptions());

        OptionFixed::dispatch($poll->id, $events->pluck('origin_poll_option_id')->all(), $events->pluck('id')->all());
        DashboardChanged::forItem($poll);

        if ($poll->event_id !== null) {
            EventUpdated::dispatch($poll->event_id);
        }

        $events->each(fn (Event $event) => $sendNotification->handle(new DateFixed($event, $request->user())));
        $sendMail->handle($poll, $events, $request->user());

        return to_route('polls.show', $poll)->with('success', trans_choice('ui.poll.fix.fixed', $events->count()));
    }

    /**
     * Reopen the poll to new responses; events fixed from it stay (F-08).
     */
    public function reopen(Poll $poll): RedirectResponse
    {
        $this->authorize('update', $poll);

        $poll->reopen();

        PollChanged::dispatch($poll->id);
        DashboardChanged::forItem($poll);

        return back(fallback: route('polls.show', $poll))->with('success', __('ui.poll.reopened.toast'));
    }

    /**
     * Withdraw from the poll: the own responses go and the poll leaves the own dashboard (F-08, F-22).
     */
    public function withdraw(Request $request, Poll $poll, WithdrawFromPoll $withdrawFromPoll): RedirectResponse
    {
        $this->authorize('withdraw', $poll);

        // Resolved before withdrawing, since a standalone poll's circle no longer holds the person afterwards.
        $recipients = DashboardChanged::recipientsOf($poll);
        $withdrawFromPoll->handle($poll, $request->user());

        PollChanged::dispatch($poll->id);
        DashboardChanged::dispatch($poll->group_id, $recipients);

        return to_route('dashboard')->with('success', __('ui.poll.withdrawn', ['poll' => $poll->title]));
    }

    /**
     * Delete the poll, its options, and every response on them; a follow-up poll returns to its event.
     *
     * A standalone poll returns to the dashboard, since it has no group page (F-29).
     */
    public function destroy(Poll $poll): RedirectResponse
    {
        $this->authorize('delete', $poll);

        $group = $poll->group;
        $event = $poll->event;
        $recipients = DashboardChanged::recipientsOf($poll);
        $poll->delete();

        PollChanged::dispatch($poll->id, deleted: true);
        DashboardChanged::dispatch($poll->group_id, $recipients);

        if ($event !== null) {
            EventUpdated::dispatch($event->id);

            return to_route('events.show', $event)->with('success', __('ui.poll.settings.deleted'));
        }

        if ($group === null) {
            return to_route('dashboard')->with('success', __('ui.poll.settings.deleted'));
        }

        return to_route('groups.show', $group)->with('success', __('ui.poll.settings.deleted'));
    }
}

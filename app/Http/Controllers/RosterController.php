<?php

namespace App\Http\Controllers;

use App\Data\RosterFormData;
use App\Data\RosterShowData;
use App\Events\EventUpdated;
use App\Http\Requests\Rosters\StoreRosterRequest;
use App\Http\Requests\Rosters\UpdateRosterRequest;
use App\Models\Event;
use App\Models\Group;
use App\Models\Roster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the "New list" page, placed in the group or event given in the query (F-13).
     */
    public function create(Request $request): Response
    {
        $group = $request->filled('group') ? Group::query()->where('slug', $request->string('group'))->firstOrFail() : null;
        $event = $request->filled('event') ? Event::query()->where('slug', $request->string('event'))->firstOrFail() : null;

        if ($event !== null) {
            $this->authorize('createForEvent', [Roster::class, $event]);
        }

        if ($group !== null) {
            $this->authorize('createForGroup', [Roster::class, $group]);
        }

        return Inertia::render('Rosters/Create', [
            'form' => RosterFormData::forCreate($request->user(), $group, $event),
        ]);
    }

    /**
     * Create a new roster: standalone, attached to a group or attached to an event (F-13).
     */
    public function store(StoreRosterRequest $request): RedirectResponse
    {
        $roster = Roster::query()->make($request->safe()->only(['title', 'description', 'date', 'members_can_add_items']));
        $roster->owner_id = $request->user()->getKey();
        $roster->group_id = $request->event() ? null : $request->group()?->getKey();
        $roster->event_id = $request->event()?->getKey();
        $roster->save();

        if ($roster->event_id !== null) {
            EventUpdated::dispatch($roster->event_id);
        }

        return to_route('rosters.show', $roster)->with('success', __('ui.newList.created'));
    }

    /**
     * Show the list with its items, claims and comments (F-13 to F-20).
     */
    public function show(Request $request, Roster $roster): Response
    {
        $this->authorize('view', $roster);

        return Inertia::render('Rosters/Show', [
            'page' => RosterShowData::forUser($roster, $request->user()),
        ]);
    }

    /**
     * Show the list settings: the "New list" page in edit mode.
     */
    public function edit(Request $request, Roster $roster): Response
    {
        $this->authorize('update', $roster);

        return Inertia::render('Rosters/Create', [
            'form' => RosterFormData::forEdit($roster, $request->user()),
        ]);
    }

    /**
     * Update the roster's settings and return to the list (section 6, "List edit").
     */
    public function update(UpdateRosterRequest $request, Roster $roster): RedirectResponse
    {
        $roster->update($request->safe()->only([
            'title', 'description', 'date', 'members_can_add_items', 'attendance_poll_option_id',
        ]));

        return to_route('rosters.show', $roster)->with('success', __('ui.list.settings.saved'));
    }

    /**
     * Delete the roster and return to its event, its group or the dashboard.
     */
    public function destroy(Roster $roster): RedirectResponse
    {
        $this->authorize('delete', $roster);

        $roster->loadMissing('event', 'group');
        $roster->delete();

        if ($roster->event !== null) {
            EventUpdated::dispatch($roster->event_id);

            return to_route('events.show', $roster->event)->with('success', __('ui.list.settings.deleted'));
        }

        if ($roster->group !== null) {
            return to_route('groups.show', $roster->group)->with('success', __('ui.list.settings.deleted'));
        }

        return to_route('dashboard')->with('success', __('ui.list.settings.deleted'));
    }

    /**
     * Duplicate the roster's metadata into a new, independent roster.
     */
    public function duplicate(Roster $roster): RedirectResponse
    {
        $this->authorize('duplicate', $roster);

        $copy = $roster->duplicate();

        return to_route('rosters.show', $copy)->with('success', __('ui.list.settings.duplicated'));
    }
}

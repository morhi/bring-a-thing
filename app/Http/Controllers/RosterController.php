<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rosters\StoreRosterRequest;
use App\Http\Requests\Rosters\UpdateRosterRequest;
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
     * Create a new roster, standalone or attached to a group.
     */
    public function store(StoreRosterRequest $request): RedirectResponse
    {
        $roster = Roster::query()->make($request->safe()->only(['title', 'description', 'date']));
        $roster->owner_id = $request->user()->getKey();
        $roster->group_id = $request->group()?->getKey();
        $roster->save();

        return to_route('rosters.show', $roster)->with('success', 'Roster created.');
    }

    /**
     * Show the roster: metadata and items (items arrive in Phase 4).
     */
    public function show(Request $request, Roster $roster): Response
    {
        $this->authorize('view', $roster);

        $roster->load('group.members', 'owner', 'customFields');
        $roster->load(['items' => function ($query) {
            $query->with(['claims.user', 'customFieldValues.customField'])->orderBy('date');
        }]);

        return Inertia::render('Rosters/Show', [
            'roster' => $roster,
            'canManage' => $request->user()->can('update', $roster),
            'canAddItems' => $roster->canBeAddedToBy($request->user()),
        ]);
    }

    /**
     * Show the owner-only roster settings page.
     */
    public function edit(Roster $roster): Response
    {
        $this->authorize('update', $roster);

        $roster->load('customFields', 'group');

        return Inertia::render('Rosters/Edit', [
            'roster' => $roster,
        ]);
    }

    /**
     * Update the roster's settings.
     */
    public function update(UpdateRosterRequest $request, Roster $roster): RedirectResponse
    {
        $roster->update($request->safe()->only(['title', 'description', 'date', 'members_can_add_items']));

        return to_route('rosters.edit', $roster)->with('success', 'Roster updated.');
    }

    /**
     * Delete the roster.
     */
    public function destroy(Roster $roster): RedirectResponse
    {
        $this->authorize('delete', $roster);

        $roster->delete();

        return to_route('dashboard')->with('success', 'Roster deleted.');
    }

    /**
     * Duplicate the roster's metadata into a new, independent roster.
     */
    public function duplicate(Roster $roster): RedirectResponse
    {
        $this->authorize('duplicate', $roster);

        $copy = $roster->duplicate();

        return to_route('rosters.show', $copy)->with('success', 'Roster duplicated.');
    }
}

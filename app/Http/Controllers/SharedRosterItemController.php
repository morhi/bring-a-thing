<?php

namespace App\Http\Controllers;

use App\Events\RosterItemSaved;
use App\Http\Requests\SharedRosters\StoreSharedRosterItemRequest;
use App\Models\Roster;
use Illuminate\Http\RedirectResponse;

class SharedRosterItemController extends Controller
{
    /**
     * Add a thing to a roster via its shared link.
     *
     * Only reachable when the roster's owner has turned on link sharing and
     * allowed share-link visitors to add things (Roster::canBeAddedToByShareLinkVisitor).
     * A guest is asked to log in first rather than carrying a whole add-thing
     * form through the session; the frontend opens a login dialog in place
     * of ever hitting this endpoint while signed out, and this redirect is
     * just a defensive fallback.
     */
    public function store(StoreSharedRosterItemRequest $request, string $token): RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();

        abort_unless($roster->canBeAddedToByShareLinkVisitor(), 403);

        if (! $user = $request->user()) {
            return redirect()->route('welcome')
                ->with('success', 'Log in or sign up, then you can add things to this list.');
        }

        $item = $roster->items()->create($request->safe()->only(['name', 'quantity', 'unit', 'notes']));

        RosterItemSaved::dispatch($item);

        return back()->with('success', 'Thing added.');
    }
}

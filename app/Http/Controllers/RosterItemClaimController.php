<?php

namespace App\Http\Controllers;

use App\Actions\RosterItems\ClaimRosterItem;
use App\Events\RosterItemSaved;
use App\Http\Requests\RosterItems\ClaimRosterItemRequest;
use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RosterItemClaimController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create or update the current user's claim on the item.
     */
    public function store(ClaimRosterItemRequest $request, Roster $roster, RosterItem $item, ClaimRosterItem $claimItem): RedirectResponse
    {
        $claimItem->handle($item, $request->user(), $request->input('quantity') !== null ? (float) $request->input('quantity') : null);

        RosterItemSaved::dispatch($item);

        return back()->with('success', 'Claim saved.');
    }

    /**
     * Remove the current user's claim on the item.
     */
    public function destroy(Request $request, Roster $roster, RosterItem $item): RedirectResponse
    {
        $this->authorize('claim', $item);

        $item->claims()->where('user_id', $request->user()->getKey())->delete();

        RosterItemSaved::dispatch($item);

        return back()->with('success', 'Claim removed.');
    }
}

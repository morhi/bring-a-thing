<?php

namespace App\Http\Controllers;

use App\Actions\Sharing\AddSharedRosterItem;
use App\Actions\Sharing\StorePendingSharedClaim;
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
     * A guest's validated draft joins the pending actions in the session,
     * next to any pending claims, and is added automatically after they log
     * in on the same page (see CompletePendingSharedClaim, F-19).
     */
    public function store(StoreSharedRosterItemRequest $request, string $token, AddSharedRosterItem $addItem, StorePendingSharedClaim $pendingActions): RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();

        abort_unless($roster->canBeAddedToByShareLinkVisitor(), 403);

        $draft = $request->safe()->only(['name', 'quantity', 'unit', 'notes', 'custom_fields']);

        if (! $user = $request->user()) {
            $pendingActions->queueItem($request, $roster, $draft);

            // The page opens the login with the pending note itself, so no flash here
            return back();
        }

        abort_if($roster->excludesShareLinkVisitor($user), 404);

        $addItem->handle($roster, $draft);

        return back()->with('success', __('ui.list.item.added'));
    }
}

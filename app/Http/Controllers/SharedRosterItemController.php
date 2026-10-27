<?php

namespace App\Http\Controllers;

use App\Actions\Sharing\AddSharedRosterItem;
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
     * A guest's validated draft is stashed in the session together with the
     * list, and added automatically after they log in on the same page
     * (see CompletePendingSharedClaim), like a pending claim (F-19).
     */
    public function store(StoreSharedRosterItemRequest $request, string $token, AddSharedRosterItem $addItem): RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();

        abort_unless($roster->canBeAddedToByShareLinkVisitor(), 403);

        $draft = $request->safe()->only(['name', 'quantity', 'unit', 'notes', 'custom_fields']);

        if (! $user = $request->user()) {
            $request->session()->put('pending_shared_item', ['roster_token' => $token, ...$draft]);
            $request->session()->put('pending_shared_claim', ['roster_token' => $token, 'item_id' => null, 'quantity' => null]);

            // The page opens the login with the pending note itself, so no flash here
            return back();
        }

        abort_if($roster->excludesShareLinkVisitor($user), 404);

        $addItem->handle($roster, $draft);

        return back()->with('success', __('ui.list.item.added'));
    }
}

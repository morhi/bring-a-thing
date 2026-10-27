<?php

namespace App\Http\Controllers;

use App\Actions\RosterItems\ClaimRosterItem;
use App\Actions\Sharing\StorePendingSharedClaim;
use App\Events\ClaimChanged;
use App\Http\Requests\SharedRosters\ClaimSharedRosterItemRequest;
use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SharedRosterClaimController extends Controller
{
    /**
     * Claim an item from a shared list link.
     *
     * A visitor who is already logged in claims immediately. A guest's
     * intended claim joins the pending actions in the session and completes
     * automatically after they log in or sign up (see CompletePendingSharedClaim), so
     * claiming from a shared link never requires joining the list's group.
     * Members of an event's group stay bound to the participation rules (F-13, F-15).
     */
    public function store(ClaimSharedRosterItemRequest $request, string $token, RosterItem $item, ClaimRosterItem $claimItem, StorePendingSharedClaim $pendingActions): RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();
        abort_unless($item->roster_id === $roster->getKey(), 404);

        $quantity = $request->input('quantity') !== null ? (float) $request->input('quantity') : null;

        if ($user = $request->user()) {
            abort_if($roster->excludesShareLinkVisitor($user), 404);

            if (! $roster->acceptsShareLinkClaimFrom($user)) {
                return back()->with('error', __('ui.list.claim.acceptFirst'));
            }

            $claimItem->handle($item, $user, $quantity);
            ClaimChanged::dispatch($roster->id, $item->id, $user->id);

            return back()->with('success', __('ui.list.claim.saved'));
        }

        $pendingActions->queueClaim($request, $roster, $item, $quantity);

        // The page opens the login listing the pending actions itself, so no flash here
        return back();
    }

    /**
     * Remove the logged-in visitor's own claim from a shared list (F-15, F-19).
     *
     * Guests have no claim to remove, and a share link never removes
     * other people's claims.
     */
    public function destroy(Request $request, string $token, RosterItem $item): RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();
        abort_unless($item->roster_id === $roster->getKey(), 404);

        $user = $request->user();
        abort_if($user === null || $roster->excludesShareLinkVisitor($user), 404);

        $item->claims()->where('user_id', $user->getKey())->delete();
        ClaimChanged::dispatch($roster->id, $item->id, $user->id);

        return back()->with('success', __('ui.list.claim.removed'));
    }
}

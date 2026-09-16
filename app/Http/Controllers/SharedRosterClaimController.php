<?php

namespace App\Http\Controllers;

use App\Actions\RosterItems\ClaimRosterItem;
use App\Events\RosterItemSaved;
use App\Http\Requests\SharedRosters\ClaimSharedRosterItemRequest;
use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Http\RedirectResponse;

class SharedRosterClaimController extends Controller
{
    /**
     * Claim an item from a shared list link.
     *
     * A visitor who is already logged in claims immediately. A guest's
     * intended claim is stashed in the session and resumed automatically
     * after they log in or sign up (see CompletePendingSharedClaim), so
     * claiming from a shared link never requires joining the list's group.
     */
    public function store(ClaimSharedRosterItemRequest $request, string $token, RosterItem $item, ClaimRosterItem $claimItem): RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();
        abort_unless($item->roster_id === $roster->getKey(), 404);

        $quantity = $request->input('quantity') !== null ? (float) $request->input('quantity') : null;

        if ($user = $request->user()) {
            $claimItem->handle($item, $user, $quantity);
            RosterItemSaved::dispatch($item);

            return back()->with('success', 'Claim saved.');
        }

        $request->session()->put('pending_shared_claim', [
            'roster_token' => $token,
            'item_id' => $item->getKey(),
            'quantity' => $quantity,
        ]);

        return redirect()->route('welcome')
            ->with('success', "Log in or sign up to claim \"{$item->name}\" — we'll save your claim right after.");
    }
}

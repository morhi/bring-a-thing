<?php

namespace App\Actions\Sharing;

use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Http\Request;

class StorePendingSharedClaim
{
    /**
     * Stash a shared list a visitor was on, and optionally a claim they
     * started, before logging in.
     *
     * Called from the login/registration endpoints themselves when the
     * request carries pending-claim fields (set by a login dialog opened
     * from a shared list page), so the visitor lands back on that list once
     * authenticated, with any claim completed automatically (see
     * CompletePendingSharedClaim). A null item ID just means "return me to
     * this list," e.g. when logging in to gain add-things access rather
     * than to claim something specific. Silently does nothing if the
     * roster/item combination is invalid.
     */
    public function handle(Request $request, ?string $rosterToken, ?int $itemId, ?float $quantity): void
    {
        if ($rosterToken === null) {
            return;
        }

        $roster = Roster::query()->where('share_token', $rosterToken)->first();

        if (! $roster) {
            return;
        }

        if ($itemId !== null && ! RosterItem::query()->where('roster_id', $roster->getKey())->whereKey($itemId)->exists()) {
            return;
        }

        $request->session()->put('pending_shared_claim', [
            'roster_token' => $rosterToken,
            'item_id' => $itemId,
            'quantity' => $quantity,
        ]);
    }
}

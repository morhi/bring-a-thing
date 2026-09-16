<?php

namespace App\Actions\Sharing;

use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Http\Request;

class StorePendingSharedClaim
{
    /**
     * Stash a claim a visitor started on a shared list before logging in.
     *
     * Called from the login/registration endpoints themselves when the
     * request carries pending-claim fields (set by the login dialog opened
     * from a shared list page), so the claim can be completed automatically
     * once the visitor is authenticated (see CompletePendingSharedClaim).
     * Silently does nothing if the roster/item combination is invalid.
     */
    public function handle(Request $request, ?string $rosterToken, ?int $itemId, ?float $quantity): void
    {
        if ($rosterToken === null || $itemId === null) {
            return;
        }

        $roster = Roster::query()->where('share_token', $rosterToken)->first();
        $item = $roster ? RosterItem::query()->where('roster_id', $roster->getKey())->find($itemId) : null;

        if (! $roster || ! $item) {
            return;
        }

        $request->session()->put('pending_shared_claim', [
            'roster_token' => $rosterToken,
            'item_id' => $itemId,
            'quantity' => $quantity,
        ]);
    }
}

<?php

namespace App\Actions\Sharing;

use App\Actions\RosterItems\ClaimRosterItem;
use App\Events\RosterItemSaved;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompletePendingSharedClaim
{
    public function __construct(private readonly ClaimRosterItem $claimItem) {}

    /**
     * Return a guest to the shared list they were on before logging in,
     * completing a claim they started there if one is pending.
     *
     * The session key is set by StorePendingSharedClaim, either from
     * SharedRosterClaimController::store (claiming a specific item) or from
     * a plain "log in to gain access" dialog with no item attached; this
     * runs right after login so the one extra step (log in or sign up) is
     * all it takes.
     */
    public function handle(Request $request, User $user): ?RedirectResponse
    {
        $intent = $request->session()->pull('pending_shared_claim');

        if (! is_array($intent)) {
            return null;
        }

        $roster = Roster::query()->where('share_token', $intent['roster_token'] ?? null)->first();

        if (! $roster) {
            return null;
        }

        $itemId = $intent['item_id'] ?? null;
        $item = $itemId !== null ? RosterItem::query()->where('roster_id', $roster->getKey())->find($itemId) : null;

        if ($itemId !== null && ! $item) {
            return null;
        }

        if (! $item) {
            return redirect()->route('shared-rosters.show', $roster->share_token);
        }

        try {
            $this->claimItem->handle($item, $user, $intent['quantity'] ?? null);
        } catch (ValidationException $exception) {
            return redirect()->route('shared-rosters.show', $roster->share_token)
                ->with('error', $exception->validator->errors()->first());
        }

        RosterItemSaved::dispatch($item);

        return redirect()->route('shared-rosters.show', $roster->share_token)->with('success', 'Claim saved.');
    }
}

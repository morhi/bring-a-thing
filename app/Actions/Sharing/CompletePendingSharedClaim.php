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
     * Finish a claim a guest started on a shared list before logging in.
     *
     * The session key is set by SharedRosterClaimController::store when an
     * unauthenticated visitor tries to claim an item; this runs right after
     * login so the one extra step (log in or sign up) is all it takes.
     */
    public function handle(Request $request, User $user): ?RedirectResponse
    {
        $intent = $request->session()->pull('pending_shared_claim');

        if (! is_array($intent)) {
            return null;
        }

        $roster = Roster::query()->where('share_token', $intent['roster_token'] ?? null)->first();
        $item = $roster ? RosterItem::query()->where('roster_id', $roster->getKey())->find($intent['item_id'] ?? null) : null;

        if (! $roster || ! $item) {
            return null;
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

<?php

namespace App\Actions\Sharing;

use App\Actions\RosterItems\ClaimRosterItem;
use App\Events\ClaimChanged;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompletePendingSharedClaim
{
    public function __construct(
        private readonly ClaimRosterItem $claimItem,
        private readonly AddSharedRosterItem $addItem,
    ) {}

    /**
     * Return a guest to the shared list they were on before logging in,
     * completing a claim they started there if one is pending.
     *
     * The session key is set by StorePendingSharedClaim, either from
     * SharedRosterClaimController::store (claiming a specific item) or from
     * a plain "log in to gain access" dialog with no item attached; this
     * runs right after login so the one extra step (log in or sign up) is
     * all it takes. An item draft a guest submitted on that list
     * (SharedRosterItemController::store) is added the same way (F-19).
     */
    public function handle(Request $request, User $user): ?RedirectResponse
    {
        $draft = $request->session()->pull('pending_shared_item');
        $intent = $request->session()->pull('pending_shared_claim');

        if (is_array($draft) && ($redirect = $this->completeAdd($draft, $user))) {
            return $redirect;
        }

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

        if (! $roster->acceptsShareLinkClaimFrom($user) || $roster->excludesShareLinkVisitor($user)) {
            return redirect()->route('shared-rosters.show', $roster->share_token)
                ->with('error', __('ui.list.claim.acceptFirst'));
        }

        try {
            $this->claimItem->handle($item, $user, $intent['quantity'] ?? null);
        } catch (ValidationException $exception) {
            return redirect()->route('shared-rosters.show', $roster->share_token)
                ->with('error', $exception->validator->errors()->first());
        }

        ClaimChanged::dispatch($roster->id, $item->id, $user->id);

        return redirect()->route('shared-rosters.show', $roster->share_token)->with('success', __('ui.list.claim.saved'));
    }

    /**
     * Add an item draft a guest submitted before logging in, unless the
     * list stopped accepting additions or hides itself from this person.
     *
     * @param  array<string, mixed>  $draft
     */
    private function completeAdd(array $draft, User $user): ?RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $draft['roster_token'] ?? null)->first();

        if (! $roster || ! is_string($draft['name'] ?? null)) {
            return null;
        }

        if (! $roster->canBeAddedToByShareLinkVisitor() || $roster->excludesShareLinkVisitor($user)) {
            return redirect()->route('shared-rosters.show', $roster->share_token)
                ->with('error', __('ui.list.guest.addClosed'));
        }

        $this->addItem->handle($roster, $draft);

        return redirect()->route('shared-rosters.show', $roster->share_token)->with('success', __('ui.list.item.added'));
    }
}

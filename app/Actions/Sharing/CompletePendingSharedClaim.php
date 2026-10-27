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
        private readonly StorePendingSharedClaim $pendingActions,
    ) {}

    /**
     * Return a guest to the shared list they were on before logging in,
     * completing every claim and item add they started there (F-19).
     *
     * StorePendingSharedClaim keeps the actions in the order the guest took
     * them; this runs right after login so the one extra step (log in or
     * sign up) is all it takes. Each action stands on its own: a claim that
     * fails because the amount is gone meanwhile does not drop an item add,
     * and the flash lists what completed and what did not.
     */
    public function handle(Request $request, User $user): ?RedirectResponse
    {
        ['actions' => $actions, 'roster_token' => $returnToken] = $this->pendingActions->pull($request);

        $completed = [];
        $errors = [];
        $lastRoster = null;

        foreach ($actions as $action) {
            $roster = Roster::query()->where('share_token', $action['roster_token'] ?? null)->first();

            if (! $roster) {
                continue;
            }

            $lastRoster = $roster;
            $result = ($action['kind'] ?? null) === 'add'
                ? $this->completeAdd($roster, $action, $user)
                : $this->completeClaim($roster, $action, $user);

            if (isset($result['success'])) {
                $completed[] = $result['success'];
            }

            if (isset($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        $roster = ($returnToken !== null ? Roster::query()->where('share_token', $returnToken)->first() : null) ?? $lastRoster;

        if (! $roster) {
            return null;
        }

        $redirect = redirect()->route('shared-rosters.show', $roster->share_token);

        if ($completed !== []) {
            $redirect->with('success', __('ui.list.guest.done', ['actions' => implode(', ', $completed)]));
        }

        if ($errors !== []) {
            $redirect->with('error', implode(' ', $errors));
        }

        return $redirect;
    }

    /**
     * Complete a claim a guest started before logging in.
     *
     * @param  array<string, mixed>  $intent
     * @return array{success?: string, error?: string}
     */
    private function completeClaim(Roster $roster, array $intent, User $user): array
    {
        $item = RosterItem::query()->where('roster_id', $roster->getKey())->find($intent['item_id'] ?? null);

        if (! $item) {
            return ['error' => __('ui.list.guest.claimGone')];
        }

        if (! $roster->acceptsShareLinkClaimFrom($user) || $roster->excludesShareLinkVisitor($user)) {
            return ['error' => __('ui.list.claim.acceptFirst')];
        }

        $quantity = $intent['quantity'] ?? null;

        try {
            $this->claimItem->handle($item, $user, is_numeric($quantity) ? (float) $quantity : null);
        } catch (ValidationException $exception) {
            return ['error' => __('ui.list.guest.claimFailed', [
                'item' => $item->name,
                'reason' => $exception->validator->errors()->first(),
            ])];
        }

        ClaimChanged::dispatch($roster->id, $item->id, $user->id);

        return ['success' => __('ui.list.guest.doneClaim', ['item' => $item->name])];
    }

    /**
     * Add an item draft a guest submitted before logging in, unless the
     * list stopped accepting additions or hides itself from this person.
     *
     * @param  array<string, mixed>  $draft
     * @return array{success?: string, error?: string}
     */
    private function completeAdd(Roster $roster, array $draft, User $user): array
    {
        if (! is_string($draft['name'] ?? null)) {
            return [];
        }

        if (! $roster->canBeAddedToByShareLinkVisitor() || $roster->excludesShareLinkVisitor($user)) {
            return ['error' => __('ui.list.guest.addClosed')];
        }

        $this->addItem->handle($roster, $draft);

        return ['success' => __('ui.list.guest.doneAdd', ['item' => $draft['name']])];
    }
}

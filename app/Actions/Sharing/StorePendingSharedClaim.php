<?php

namespace App\Actions\Sharing;

use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Http\Request;

class StorePendingSharedClaim
{
    /**
     * Session key of the ordered list of a guest's pending claims and item adds (F-19).
     */
    public const ACTIONS_KEY = 'pending_shared_actions';

    /**
     * Session key of the shared list a guest returns to after logging in.
     */
    public const ROSTER_KEY = 'pending_shared_roster';

    /**
     * Session keys of the single item draft and claim before the ordered list.
     */
    private const LEGACY_ITEM_KEY = 'pending_shared_item';

    private const LEGACY_CLAIM_KEY = 'pending_shared_claim';

    /**
     * Stash a shared list a visitor was on, and optionally a claim they
     * started, before logging in.
     *
     * Called from the login/registration endpoints themselves when the
     * request carries pending-claim fields (set by a login dialog opened
     * from a shared list page), so the visitor lands back on that list once
     * authenticated, with any pending actions completed automatically (see
     * CompletePendingSharedClaim). A null item ID just means "return me to
     * this list," e.g. when the claims and adds already wait in the session.
     * Silently does nothing if the roster/item combination is invalid.
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

        if ($itemId === null) {
            $request->session()->put(self::ROSTER_KEY, $rosterToken);

            return;
        }

        $item = RosterItem::query()->where('roster_id', $roster->getKey())->find($itemId);

        if (! $item) {
            return;
        }

        $this->queueClaim($request, $roster, $item, $quantity);
    }

    /**
     * Append a guest's claim to the pending actions.
     *
     * Claiming the same item again replaces the earlier quantity in place,
     * since a person holds one claim per item (F-15).
     */
    public function queueClaim(Request $request, Roster $roster, RosterItem $item, ?float $quantity): void
    {
        $actions = $this->actions($request);

        foreach ($actions as $index => $action) {
            if (($action['kind'] ?? null) !== 'claim' || ($action['roster_token'] ?? null) !== $roster->share_token || ($action['item_id'] ?? null) !== $item->getKey()) {
                continue;
            }

            $actions[$index]['quantity'] = $quantity;

            $this->store($request, $roster, $actions);

            return;
        }

        $actions[] = [
            'kind' => 'claim',
            'roster_token' => $roster->share_token,
            'item_id' => $item->getKey(),
            'quantity' => $quantity,
        ];

        $this->store($request, $roster, $actions);
    }

    /**
     * Append a guest's validated item draft to the pending actions.
     *
     * @param  array<string, mixed>  $draft  name, quantity, unit, notes and custom_fields keyed by field ID
     */
    public function queueItem(Request $request, Roster $roster, array $draft): void
    {
        $actions = $this->actions($request);
        $actions[] = ['kind' => 'add', 'roster_token' => $roster->share_token, ...$draft];

        $this->store($request, $roster, $actions);
    }

    /**
     * Read the pending actions from the session, ignoring anything malformed.
     *
     * Sessions started before the ordered list existed hold at most one item
     * draft and one claim under the old keys; they still complete, the
     * draft first as before.
     *
     * @return list<array<string, mixed>>
     */
    public function actions(Request $request): array
    {
        $actions = $request->session()->get(self::ACTIONS_KEY);
        $actions = is_array($actions) ? array_values(array_filter($actions, 'is_array')) : [];
        $legacyDraft = $request->session()->get(self::LEGACY_ITEM_KEY);
        $legacyClaim = $request->session()->get(self::LEGACY_CLAIM_KEY);

        if (is_array($legacyDraft)) {
            $actions[] = ['kind' => 'add', ...$legacyDraft];
        }

        if (is_array($legacyClaim) && ($legacyClaim['item_id'] ?? null) !== null) {
            $actions[] = ['kind' => 'claim', ...$legacyClaim];
        }

        return $actions;
    }

    /**
     * Take the pending actions and the list to return to out of the session.
     *
     * @return array{actions: list<array<string, mixed>>, roster_token: ?string}
     */
    public function pull(Request $request): array
    {
        $actions = $this->actions($request);
        $legacyClaim = $request->session()->pull(self::LEGACY_CLAIM_KEY);
        $rosterToken = $request->session()->pull(self::ROSTER_KEY) ?? (is_array($legacyClaim) ? ($legacyClaim['roster_token'] ?? null) : null);

        $request->session()->forget([self::ACTIONS_KEY, self::LEGACY_ITEM_KEY]);

        return ['actions' => $actions, 'roster_token' => is_string($rosterToken) ? $rosterToken : null];
    }

    /**
     * Save the pending actions and remember the list to return to.
     *
     * @param  list<array<string, mixed>>  $actions
     */
    private function store(Request $request, Roster $roster, array $actions): void
    {
        $request->session()->put(self::ACTIONS_KEY, $actions);
        $request->session()->put(self::ROSTER_KEY, $roster->share_token);

        // actions() already folded any old-style entries into the list
        $request->session()->forget([self::LEGACY_ITEM_KEY, self::LEGACY_CLAIM_KEY]);
    }
}

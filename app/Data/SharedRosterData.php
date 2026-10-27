<?php

namespace App\Data;

use App\Actions\Auth\SendMagicLink;
use App\Models\CustomField;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Spatie\LaravelData\Data;

class SharedRosterData extends Data
{
    /**
     * @param  array<int, CustomFieldData>  $custom_fields
     * @param  array<int, RosterItemData>  $items
     * @param  array<int, SharedPendingActionData>  $pending_actions
     */
    public function __construct(
        public string $token,
        public int $link_expires_in_minutes,
        public string $title,
        public ?string $description,
        public ?string $date,
        public bool $is_past,
        public bool $can_add_items,
        public bool $is_all_taken,
        public array $custom_fields,
        public array $items,
        public array $pending_actions,
    ) {}

    /**
     * Build the shared list for a share link visitor (F-19).
     *
     * Shows names but no email addresses and no user IDs. The token and
     * the link lifetime feed the login on the same page (F-01). A
     * logged-in viewer's own claims are marked so they can change them. A
     * guest's pending claims and adds on this list feed the login panel.
     *
     * @param  list<array<string, mixed>>  $pendingActions  see StorePendingSharedClaim
     */
    public static function fromRoster(Roster $roster, ?User $viewer = null, array $pendingActions = []): self
    {
        $roster->load([
            'customFields',
            'items' => fn ($query) => $query
                ->with(['claims.user:id,name', 'customFieldValues', 'attendancePollOption'])
                ->orderBy('date'),
        ]);

        $items = $roster->items->map(fn (RosterItem $item) => RosterItemData::forShareLink($item, $viewer));

        return new self(
            token: (string) $roster->share_token,
            link_expires_in_minutes: SendMagicLink::EXPIRES_IN_MINUTES,
            title: $roster->title,
            description: $roster->description,
            date: $roster->date?->format('Y-m-d'),
            is_past: $roster->isPast(),
            can_add_items: $roster->canBeAddedToByShareLinkVisitor(),
            is_all_taken: $items->isNotEmpty() && $items->every(fn (RosterItemData $item) => $item->is_fully_claimed),
            custom_fields: $roster->customFields->map(fn (CustomField $field) => CustomFieldData::fromField($field))->all(),
            items: $items->all(),
            pending_actions: self::pendingActionsOn($roster, $pendingActions),
        );
    }

    /**
     * Name the pending actions that belong to this list, in order.
     *
     * A claim on an item deleted meanwhile is left out, since it cannot complete.
     *
     * @param  list<array<string, mixed>>  $pendingActions
     * @return array<int, SharedPendingActionData>
     */
    private static function pendingActionsOn(Roster $roster, array $pendingActions): array
    {
        $names = [];

        foreach ($pendingActions as $action) {
            if (($action['roster_token'] ?? null) !== $roster->share_token) {
                continue;
            }

            if (($action['kind'] ?? null) === 'add') {
                $names[] = new SharedPendingActionData(kind: 'add', name: (string) ($action['name'] ?? ''));

                continue;
            }

            $item = $roster->items->firstWhere('id', $action['item_id'] ?? null);

            if ($item) {
                $names[] = new SharedPendingActionData(kind: 'claim', name: $item->name);
            }
        }

        return $names;
    }
}

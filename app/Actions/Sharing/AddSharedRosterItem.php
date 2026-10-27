<?php

namespace App\Actions\Sharing;

use App\Events\ItemChanged;
use App\Models\Roster;
use App\Models\RosterItem;

class AddSharedRosterItem
{
    /**
     * Add an item from the shared list page (F-14, F-19).
     *
     * Used both for a logged-in visitor's add and for the draft a guest
     * submitted before logging in (see CompletePendingSharedClaim). Custom
     * field values outside the list's own fields are dropped, since a
     * stashed draft may outlive a field the owner deleted meanwhile.
     *
     * @param  array<string, mixed>  $draft  name, quantity, unit, notes and custom_fields keyed by field ID
     */
    public function handle(Roster $roster, array $draft): RosterItem
    {
        /** @var RosterItem $item */
        $item = $roster->items()->create([
            'name' => $draft['name'],
            'quantity' => $draft['quantity'] ?? null,
            'unit' => $draft['unit'] ?? null,
            'notes' => $draft['notes'] ?? null,
        ]);

        $fieldIds = $roster->customFields()->pluck('id')->all();

        foreach ($draft['custom_fields'] ?? [] as $customFieldId => $value) {
            if (! in_array((int) $customFieldId, $fieldIds, true)) {
                continue;
            }

            $item->customFieldValues()->updateOrCreate(
                ['custom_field_id' => (int) $customFieldId],
                ['value' => $value],
            );
        }

        ItemChanged::dispatch($roster->id, $item->id);

        return $item;
    }
}

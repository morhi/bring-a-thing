<?php

namespace App\Http\Controllers;

use App\Events\RosterItemDeleted;
use App\Events\RosterItemSaved;
use App\Http\Requests\RosterItems\StoreRosterItemRequest;
use App\Http\Requests\RosterItems\UpdateRosterItemRequest;
use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class RosterItemController extends Controller
{
    use AuthorizesRequests;

    /**
     * Add an item to the roster.
     */
    public function store(StoreRosterItemRequest $request, Roster $roster): RedirectResponse
    {
        $item = $roster->items()->create($request->safe()->only(['name', 'quantity', 'unit', 'notes', 'date']));

        $this->syncCustomFieldValues($item, $request->input('custom_fields', []));

        RosterItemSaved::dispatch($item);

        return back()->with('success', 'Item added.');
    }

    /**
     * Update the item.
     */
    public function update(UpdateRosterItemRequest $request, Roster $roster, RosterItem $item): RedirectResponse
    {
        $item->update($request->safe()->only(['name', 'quantity', 'unit', 'notes', 'date']));

        $this->syncCustomFieldValues($item, $request->input('custom_fields', []));

        RosterItemSaved::dispatch($item);

        return back()->with('success', 'Item updated.');
    }

    /**
     * Delete the item.
     */
    public function destroy(Roster $roster, RosterItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $item->delete();

        RosterItemDeleted::dispatch($roster->id, $item->id);

        return back()->with('success', 'Item removed.');
    }

    /**
     * Save the item's custom field values, keyed by custom_field_id.
     *
     * @param  array<int|string, string|null>  $values
     */
    private function syncCustomFieldValues(RosterItem $item, array $values): void
    {
        foreach ($values as $customFieldId => $value) {
            $item->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $customFieldId],
                ['value' => $value],
            );
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomFields\StoreCustomFieldRequest;
use App\Http\Requests\CustomFields\UpdateCustomFieldRequest;
use App\Models\CustomField;
use App\Models\Roster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class CustomFieldController extends Controller
{
    use AuthorizesRequests;

    /**
     * Define a new custom field on the roster.
     */
    public function store(StoreCustomFieldRequest $request, Roster $roster): RedirectResponse
    {
        $roster->customFields()->create($request->safe()->only(['name']));

        return back()->with('success', 'Custom field added.');
    }

    /**
     * Rename the custom field.
     */
    public function update(UpdateCustomFieldRequest $request, Roster $roster, CustomField $customField): RedirectResponse
    {
        $customField->update($request->safe()->only(['name']));

        return back()->with('success', 'Custom field updated.');
    }

    /**
     * Delete the custom field, and any item values recorded for it.
     */
    public function destroy(Roster $roster, CustomField $customField): RedirectResponse
    {
        $this->authorize('delete', $customField);

        $customField->delete();

        return back()->with('success', 'Custom field removed.');
    }
}

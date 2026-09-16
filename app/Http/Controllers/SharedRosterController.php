<?php

namespace App\Http\Controllers;

use App\Models\Roster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SharedRosterController extends Controller
{
    /**
     * Show a shared, read-only view of the roster to anyone with the link.
     *
     * A visitor who is already authenticated and can view the roster the
     * normal way (owner or group member) is sent to the regular roster page
     * instead, since that gives them the full, live-updating experience.
     * Claimer identities are reduced to a display name only, since this page
     * carries no authentication boundary at all.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $roster = Roster::query()->where('share_token', $token)->firstOrFail();

        $user = $request->user();
        if ($user && $user->can('view', $roster)) {
            return redirect()->route('rosters.show', $roster);
        }

        $roster->load([
            'customFields',
            'items' => fn ($query) => $query
                ->with(['claims.user:id,name', 'customFieldValues.customField'])
                ->orderBy('date'),
        ]);

        return Inertia::render('Shared/RosterShow', [
            'token' => $token,
            'roster' => [
                'title' => $roster->title,
                'description' => $roster->description,
                'date' => $roster->date?->toDateString(),
                'custom_fields' => $roster->customFields->map(fn ($field) => [
                    'id' => $field->id,
                    'name' => $field->name,
                ]),
                'items' => $roster->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'notes' => $item->notes,
                    'date' => $item->date?->toDateString(),
                    'is_past' => $item->is_past,
                    'custom_field_values' => $item->customFieldValues->map(fn ($value) => [
                        'id' => $value->id,
                        'value' => $value->value,
                        'custom_field_id' => $value->custom_field_id,
                    ]),
                    'claims' => $item->claims->map(fn ($claim) => [
                        'id' => $claim->id,
                        'quantity' => $claim->quantity,
                        'user_name' => $claim->user->name ?? 'Someone',
                    ]),
                ]),
            ],
        ]);
    }
}

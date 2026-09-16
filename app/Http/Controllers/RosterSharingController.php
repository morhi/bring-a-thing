<?php

namespace App\Http\Controllers;

use App\Models\Roster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class RosterSharingController extends Controller
{
    use AuthorizesRequests;

    /**
     * Turn on link sharing for the roster.
     */
    public function store(Roster $roster): RedirectResponse
    {
        $this->authorize('update', $roster);

        $roster->enableSharing();

        return back()->with('success', 'Sharing enabled. Anyone with the link can view and claim items.');
    }

    /**
     * Replace the share link, invalidating the previous one.
     */
    public function regenerate(Roster $roster): RedirectResponse
    {
        $this->authorize('update', $roster);

        $roster->regenerateShareToken();

        return back()->with('success', 'Share link regenerated. The old link no longer works.');
    }

    /**
     * Turn off link sharing for the roster.
     */
    public function destroy(Roster $roster): RedirectResponse
    {
        $this->authorize('update', $roster);

        $roster->disableSharing();

        return back()->with('success', 'Sharing disabled.');
    }
}

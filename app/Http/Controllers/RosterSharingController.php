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

        return back()->with('success', __('ui.list.share.enabled'));
    }

    /**
     * Replace the share link, invalidating the previous one.
     */
    public function regenerate(Roster $roster): RedirectResponse
    {
        $this->authorize('update', $roster);

        $roster->regenerateShareToken();

        return back()->with('success', __('ui.list.share.regenerated'));
    }

    /**
     * Turn off link sharing for the roster.
     */
    public function destroy(Roster $roster): RedirectResponse
    {
        $this->authorize('update', $roster);

        $roster->disableSharing();

        return back()->with('success', __('ui.list.share.disabled'));
    }
}

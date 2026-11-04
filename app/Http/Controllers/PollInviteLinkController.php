<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class PollInviteLinkController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create the poll's invite link on first use (F-27).
     */
    public function store(Poll $poll): RedirectResponse
    {
        $this->authorize('manageInviteLink', $poll);

        $poll->enableInviteLink();

        return back()->with('success', __('ui.entityLink.created'));
    }

    /**
     * Replace the invite link, so the old link shows "Not found"; people who joined keep their access.
     */
    public function regenerate(Poll $poll): RedirectResponse
    {
        $this->authorize('manageInviteLink', $poll);

        $poll->regenerateInviteToken();

        return back()->with('success', __('ui.entityLink.regenerated'));
    }

    /**
     * Revoke the invite link, so it shows "Not found"; people who joined keep their access.
     */
    public function destroy(Poll $poll): RedirectResponse
    {
        $this->authorize('manageInviteLink', $poll);

        $poll->revokeInviteLink();

        return back()->with('success', __('ui.entityLink.revoked'));
    }
}

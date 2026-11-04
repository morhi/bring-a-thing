<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class EventInviteLinkController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create the event's invite link on first use (F-26).
     */
    public function store(Event $event): RedirectResponse
    {
        $this->authorize('manageInviteLink', $event);

        $event->enableInviteLink();

        return back()->with('success', __('ui.entityLink.created'));
    }

    /**
     * Replace the invite link, so the old link shows "Not found"; people who joined keep their access.
     */
    public function regenerate(Event $event): RedirectResponse
    {
        $this->authorize('manageInviteLink', $event);

        $event->regenerateInviteToken();

        return back()->with('success', __('ui.entityLink.regenerated'));
    }

    /**
     * Revoke the invite link, so it shows "Not found"; people who joined keep their access.
     */
    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('manageInviteLink', $event);

        $event->revokeInviteLink();

        return back()->with('success', __('ui.entityLink.revoked'));
    }
}

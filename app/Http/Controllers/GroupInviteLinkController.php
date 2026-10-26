<?php

namespace App\Http\Controllers;

use App\Http\Requests\Groups\UpdateGroupInviteLinkRequest;
use App\Models\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class GroupInviteLinkController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create the group's invite link on first use of "Copy invite link" (F-04).
     */
    public function store(Group $group): RedirectResponse
    {
        $this->authorize('manageInviteLink', $group);

        $group->enableInviteLink();

        return back()->with('success', 'Invite link created.');
    }

    /**
     * Switch the invite link between open (name only) and closed (email login first).
     */
    public function update(UpdateGroupInviteLinkRequest $request, Group $group): RedirectResponse
    {
        $group->update(['invite_link_locked' => $request->boolean('locked')]);

        return back()->with('success', 'Invite link updated.');
    }

    /**
     * Replace the invite link, so the old link shows "Not found".
     */
    public function regenerate(Group $group): RedirectResponse
    {
        $this->authorize('manageInviteLink', $group);

        $group->regenerateInviteToken();

        return back()->with('success', 'Invite link regenerated. The old link no longer works.');
    }

    /**
     * Revoke the invite link, so it shows "Not found".
     */
    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('manageInviteLink', $group);

        $group->revokeInviteLink();

        return back()->with('success', 'Invite link revoked.');
    }
}

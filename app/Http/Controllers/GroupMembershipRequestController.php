<?php

namespace App\Http\Controllers;

use App\Actions\Groups\RequestGroupMembership;
use App\Enums\GroupJoinMethod;
use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupMembershipRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * Ask the owner for regular membership as a guest of the group (F-03).
     */
    public function store(Request $request, Group $group, RequestGroupMembership $requestMembership): RedirectResponse
    {
        $this->authorize('requestMembership', $group);

        $requestMembership->handle($group, $request->user());

        return back()->with('success', __('ui.group.requestMembership.done', ['group' => $group->name]));
    }

    /**
     * Accept a request: the guest becomes a member and the request is gone (F-03).
     *
     * A guest who became a member in the meantime is not added twice.
     */
    public function accept(Group $group, User $requester): RedirectResponse
    {
        $this->authorize('resolveMembershipRequest', $group);

        $group->membershipRequests()->where('user_id', $requester->getKey())->firstOrFail();

        if (! $group->hasMember($requester)) {
            $group->addMember($requester, GroupRole::Member, now(), GroupJoinMethod::Invite);
        }

        $group->membershipRequests()->where('user_id', $requester->getKey())->delete();

        return back()->with('success', __('ui.groupSettings.requests.accepted', ['name' => $requester->name ?? $requester->email]));
    }

    /**
     * Decline a request: it is removed and the guest stays a guest (F-03).
     */
    public function decline(Group $group, User $requester): RedirectResponse
    {
        $this->authorize('resolveMembershipRequest', $group);

        $group->membershipRequests()->where('user_id', $requester->getKey())->firstOrFail()->delete();

        return back()->with('success', __('ui.groupSettings.requests.declined', ['name' => $requester->name ?? $requester->email]));
    }
}

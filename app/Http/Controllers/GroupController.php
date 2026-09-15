<?php

namespace App\Http\Controllers;

use App\Actions\Groups\InviteMemberToGroup;
use App\Enums\GroupRole;
use App\Http\Requests\Groups\InviteGroupMemberRequest;
use App\Http\Requests\Groups\StoreGroupRequest;
use App\Http\Requests\Groups\UpdateGroupRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new group with the requesting user as owner and first member.
     */
    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $group = Group::query()->make([
            'name' => $request->string('name')->value(),
        ]);
        $group->owner_id = $request->user()->getKey();
        $group->save();

        $group->addMember($request->user(), GroupRole::Owner, now());

        return to_route('groups.show', $group)->with('success', 'Group created.');
    }

    /**
     * Show the group: members, invite form, attached lists and polls.
     */
    public function show(Request $request, Group $group): Response
    {
        $this->authorize('view', $group);

        $group->load('members');

        return Inertia::render('Groups/Show', [
            'group' => $group,
            'canManage' => $request->user()->can('update', $group),
        ]);
    }

    /**
     * Show the owner-only group settings page.
     */
    public function edit(Group $group): Response
    {
        $this->authorize('update', $group);

        return Inertia::render('Groups/Edit', [
            'group' => $group,
        ]);
    }

    /**
     * Update the group's settings.
     */
    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update([
            'name' => $request->string('name')->value(),
        ]);

        return to_route('groups.edit', $group)->with('success', 'Group updated.');
    }

    /**
     * Delete the group.
     */
    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return to_route('dashboard')->with('success', 'Group deleted.');
    }

    /**
     * Invite a member to the group by email.
     */
    public function invite(InviteGroupMemberRequest $request, Group $group, InviteMemberToGroup $inviteMember): RedirectResponse
    {
        $inviteMember->handle($group, $request->string('email')->value());

        return back()->with('success', 'An invite has been sent.');
    }

    /**
     * Remove a member from the group. The owner cannot be removed this way.
     */
    public function removeMember(Group $group, User $member): RedirectResponse
    {
        $this->authorize('removeMember', [$group, $member]);

        $group->members()->detach($member);

        return back()->with('success', 'Member removed.');
    }
}

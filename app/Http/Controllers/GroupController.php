<?php

namespace App\Http\Controllers;

use App\Actions\Groups\InviteMemberToGroup;
use App\Data\GroupFormData;
use App\Data\GroupSettingsData;
use App\Data\GroupShowData;
use App\Enums\GroupRole;
use App\Http\Requests\Groups\InviteGroupMemberRequest;
use App\Http\Requests\Groups\StoreGroupRequest;
use App\Http\Requests\Groups\UpdateGroupMemberRoleRequest;
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
     * Show the "New group" page with friend suggestions for the invitations (F-03, F-04).
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Group::class);

        return Inertia::render('Groups/Create', [
            'form' => GroupFormData::forUser($request->user()),
        ]);
    }

    /**
     * Create a new group with the requesting user as owner and first member.
     *
     * The optional invitations go out right away (F-03, F-04).
     */
    public function store(StoreGroupRequest $request, InviteMemberToGroup $inviteMember): RedirectResponse
    {
        $group = Group::query()->make([
            'name' => $request->string('name')->value(),
        ]);
        $group->owner_id = $request->user()->getKey();
        $group->save();

        $group->addMember($request->user(), GroupRole::Owner, now());

        $invites = $this->sendInvites($group, $request->validated('invites') ?? [], $request->user(), $inviteMember);

        if ($invites === 0) {
            return to_route('groups.show', $group)->with('success', __('ui.newGroup.created'));
        }

        return to_route('groups.show', $group)->with('success', trans_choice('ui.newGroup.createdInvited', $invites, ['n' => $invites]));
    }

    /**
     * Show the group: members with invite, polls, events and group lists.
     */
    public function show(Request $request, Group $group): Response
    {
        $this->authorize('view', $group);

        return Inertia::render('Groups/Show', [
            'page' => GroupShowData::forUser($group, $request->user()),
        ]);
    }

    /**
     * Show the group settings page to owners and admins.
     *
     * Owner-only actions (rename, option, roles, delete) are flagged in
     * permissions so the page hides them from admins (F-03).
     */
    public function edit(Request $request, Group $group): Response
    {
        $this->authorize('manageSettings', $group);

        return Inertia::render('Groups/Edit', [
            'page' => GroupSettingsData::forUser($group, $request->user()),
        ]);
    }

    /**
     * Update the group's name and the option "Members can set the participation status for others".
     */
    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $group->update($request->safe()->only(['name', 'members_can_set_status']));

        return to_route('groups.edit', $group)->with('success', __('ui.settings.saved'));
    }

    /**
     * Delete the group.
     */
    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return to_route('dashboard')->with('success', __('ui.groupSettings.deleted'));
    }

    /**
     * Invite the people selected in the invite field by email (F-04).
     */
    public function invite(InviteGroupMemberRequest $request, Group $group, InviteMemberToGroup $inviteMember): RedirectResponse
    {
        $invites = $this->sendInvites($group, $request->validated('invites'), $request->user(), $inviteMember);

        return back()->with('success', trans_choice('ui.group.invite.sent', $invites, ['n' => $invites]));
    }

    /**
     * Remove a member from the group. The owner cannot be removed this way.
     */
    public function removeMember(Group $group, User $member): RedirectResponse
    {
        $this->authorize('removeMember', [$group, $member]);

        $group->members()->detach($member);

        return back()->with('success', __('ui.groupSettings.removed', ['name' => $member->name ?? $member->email]));
    }

    /**
     * Change a member's role (admin or member). The owner's role cannot be changed.
     */
    public function updateMemberRole(UpdateGroupMemberRoleRequest $request, Group $group, User $member): RedirectResponse
    {
        $group->members()->updateExistingPivot($member, ['role' => $request->enum('role', GroupRole::class)]);

        return back()->with('success', __('ui.groupSettings.roleChanged.'.$request->enum('role', GroupRole::class)->value, ['name' => $member->name ?? $member->email]));
    }

    /**
     * Invite each person and return how many were invited.
     *
     * @param  array<int, array{email: string, name?: string|null}>  $invites
     */
    private function sendInvites(Group $group, array $invites, User $inviter, InviteMemberToGroup $inviteMember): int
    {
        foreach ($invites as $invite) {
            $inviteMember->handle($group, $invite['email'], $invite['name'] ?? null, $inviter);
        }

        return count($invites);
    }
}

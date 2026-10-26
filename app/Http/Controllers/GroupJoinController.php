<?php

namespace App\Http\Controllers;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Actions\Groups\JoinGroupViaLink;
use App\Data\GroupJoinData;
use App\Http\Requests\Groups\JoinGroupRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GroupJoinController extends Controller
{
    /**
     * Show the join page of a group's invite link (F-04).
     *
     * An unknown, revoked or replaced token resolves to "Not found" through
     * route model binding. Existing members go straight to the group.
     */
    public function show(Request $request, Group $group): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $group->hasMember($user)) {
            return to_route('groups.show', $group);
        }

        return Inertia::render('Groups/Join', [
            'join' => GroupJoinData::fromGroup($group, $user),
        ]);
    }

    /**
     * Join the group through its invite link.
     *
     * Logged-in accounts join at once. On an open link a guest gets a
     * name-only account that stays logged in on this browser (F-01). On a
     * closed link a guest receives a login link that joins on use.
     */
    public function store(
        JoinGroupRequest $request,
        Group $group,
        JoinGroupViaLink $joinGroup,
        FindOrCreateUserByEmail $findOrCreateUser,
        SendMagicLink $sendMagicLink,
    ): RedirectResponse {
        $user = $request->user();

        if ($user !== null) {
            $joinGroup->handle($group, $user);

            return to_route('groups.show', $group)->with('success', 'You joined the group.');
        }

        if ($group->invite_link_locked) {
            $user = $findOrCreateUser->handle($request->string('email')->value(), $request->string('name')->value() ?: null);
            $sendMagicLink->handle($user, joinGroup: $group);

            return back()->with('success', 'A login link has been sent to your email address.');
        }

        $user = User::query()->create([
            'name' => $request->string('name')->value(),
            'email' => null,
            'language' => app()->getLocale(),
            'password' => null,
        ]);
        $joinGroup->handle($group, $user);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return to_route('groups.show', $group)->with('success', 'You joined the group.');
    }
}

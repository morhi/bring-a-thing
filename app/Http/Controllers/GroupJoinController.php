<?php

namespace App\Http\Controllers;

use App\Actions\Auth\FindOrCreateUserByEmail;
use App\Actions\Auth\SendMagicLink;
use App\Actions\Groups\JoinGroupViaLink;
use App\Data\GroupJoinData;
use App\Http\Requests\Groups\JoinGroupRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GroupJoinController extends Controller
{
    /**
     * Show the join page of a group's invite link (F-04).
     *
     * An unknown, revoked or replaced token renders the page's "Not found"
     * state with a 404 status, so the person learns to ask for a new link.
     * Existing members go straight to the group.
     */
    public function show(Request $request, string $token): SymfonyResponse
    {
        $group = Group::query()->where('invite_token', $token)->first();

        if ($group === null) {
            return Inertia::render('Groups/Join', ['join' => null])->toResponse($request)->setStatusCode(404);
        }

        $user = $request->user();

        if ($user !== null && $group->hasMember($user)) {
            return to_route('groups.show', $group);
        }

        return Inertia::render('Groups/Join', [
            'join' => GroupJoinData::fromGroup($group, $user),
        ])->toResponse($request);
    }

    /**
     * Join the group through its invite link.
     *
     * Logged-in accounts join at once. A guest always receives a login link
     * that joins on use, so no account is left without a way to log back in.
     */
    public function store(
        JoinGroupRequest $request,
        string $token,
        JoinGroupViaLink $joinGroup,
        FindOrCreateUserByEmail $findOrCreateUser,
        SendMagicLink $sendMagicLink,
    ): RedirectResponse {
        $group = $request->group();
        $user = $request->user();

        if ($user !== null) {
            $joinGroup->handle($group, $user);

            return to_route('groups.show', $group)->with('success', __('ui.join.joined'));
        }

        $user = $findOrCreateUser->handle($request->string('email')->value(), $request->string('name')->value() ?: null);
        $sendMagicLink->handle($user, joinGroup: $group);

        return back()->with('success', __('ui.welcome.login.sentFlash'));
    }
}

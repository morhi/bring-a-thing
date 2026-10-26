<?php

namespace App\Http\Controllers;

use App\Actions\Friends\AddFriend;
use App\Data\FriendsData;
use App\Http\Requests\Friends\StoreFriendRequest;
use App\Models\Friend;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FriendController extends Controller
{
    use AuthorizesRequests;

    /**
     * List the current user's friends (F-05).
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Friends', [
            'friends' => FriendsData::forUser($request->user()),
        ]);
    }

    /**
     * Add a friend by email, creating a shadow account for them if needed.
     */
    public function store(StoreFriendRequest $request, AddFriend $addFriend): RedirectResponse
    {
        $friend = $addFriend->handle($request->user(), $request->string('email')->value(), $request->string('name')->value() ?: null);
        $account = $friend->friendUser;

        return back()->with('success', __('ui.friends.add.added', ['name' => $friend->name ?? $account->name ?? $account->email]));
    }

    /**
     * Remove a friend entry; group memberships stay (F-05).
     */
    public function destroy(Friend $friend): RedirectResponse
    {
        $this->authorize('delete', $friend);

        $account = $friend->friendUser;
        $name = $friend->name ?? $account?->name ?? $account?->email ?? '';

        $friend->delete();

        return back()->with('success', __('ui.friends.removed', ['name' => $name]));
    }
}

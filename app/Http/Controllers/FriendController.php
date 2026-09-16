<?php

namespace App\Http\Controllers;

use App\Actions\Friends\AddFriend;
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
     * List the current user's friends.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Friends', [
            'friends' => $request->user()->friends()->latest()->get(),
        ]);
    }

    /**
     * Add a friend by email, creating a shadow account for them if needed.
     */
    public function store(StoreFriendRequest $request, AddFriend $addFriend): RedirectResponse
    {
        $addFriend->handle($request->user(), $request->string('email')->value(), $request->string('name')->value() ?: null);

        return back()->with('success', 'Friend added.');
    }

    /**
     * Remove a friend entry.
     */
    public function destroy(Friend $friend): RedirectResponse
    {
        $this->authorize('delete', $friend);

        $friend->delete();

        return back()->with('success', 'Friend removed.');
    }
}

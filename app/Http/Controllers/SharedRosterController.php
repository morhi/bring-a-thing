<?php

namespace App\Http\Controllers;

use App\Data\SharedRosterData;
use App\Models\Roster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SharedRosterController extends Controller
{
    /**
     * Show a shared, read-only view of the roster to anyone with the link (F-19).
     *
     * Whoever may see the list anyway lands on the normal list view. A
     * member of an event's group who is no participant gets "Not found",
     * so the link is no way around the participation rules (F-13). An
     * unknown, regenerated or disabled token shows the note that the link
     * is no longer valid, still with a 404 status.
     */
    public function show(Request $request, string $token): Response|RedirectResponse|SymfonyResponse
    {
        $roster = Roster::query()->where('share_token', $token)->first();

        if ($roster === null) {
            return Inertia::render('Shared/RosterInvalid')->toResponse($request)->setStatusCode(404);
        }

        $user = $request->user();

        if ($user && $user->can('view', $roster)) {
            return redirect()->route('rosters.show', $roster);
        }

        abort_if($user && $roster->excludesShareLinkVisitor($user), 404);

        return Inertia::render('Shared/RosterShow', [
            'roster' => SharedRosterData::fromRoster($roster),
        ]);
    }
}

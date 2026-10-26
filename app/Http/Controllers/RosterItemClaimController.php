<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SendInAppNotification;
use App\Actions\RosterItems\ClaimRosterItem;
use App\Events\ClaimChanged;
use App\Http\Requests\RosterItems\ClaimRosterItemRequest;
use App\Http\Requests\RosterItems\UnclaimRosterItemRequest;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use App\Notifications\ClaimedForYou;
use App\Notifications\ClaimReleased;
use Illuminate\Http\RedirectResponse;

class RosterItemClaimController extends Controller
{
    /**
     * Create or update a claim on the item, for the current user or, with manageClaims authority, another member.
     */
    public function store(ClaimRosterItemRequest $request, Roster $roster, RosterItem $item, ClaimRosterItem $claimItem, SendInAppNotification $sendNotification): RedirectResponse
    {
        $target = $request->filled('user_id') ? User::findOrFail($request->integer('user_id')) : $request->user();

        $claimItem->handle($item, $target, $request->input('quantity') !== null ? (float) $request->input('quantity') : null);

        ClaimChanged::dispatch($item->roster_id, $item->id, $target->id);

        $sendNotification->handle(new ClaimedForYou($item, $target, $request->user()));

        return back()->with('success', 'Claim saved.');
    }

    /**
     * Remove a claim on the item, for the current user or, with manageClaims authority, another member.
     */
    public function destroy(UnclaimRosterItemRequest $request, Roster $roster, RosterItem $item, SendInAppNotification $sendNotification): RedirectResponse
    {
        $targetId = $request->filled('user_id') ? $request->integer('user_id') : $request->user()->getKey();

        $released = $item->claims()->where('user_id', $targetId)->delete();

        ClaimChanged::dispatch($item->roster_id, $item->id, $targetId);

        if ($released > 0 && $targetId !== $request->user()->getKey()) {
            $sendNotification->handle(new ClaimReleased($item, User::findOrFail($targetId), $request->user()));
        }

        return back()->with('success', 'Claim removed.');
    }
}

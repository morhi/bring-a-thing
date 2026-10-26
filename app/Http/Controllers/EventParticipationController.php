<?php

namespace App\Http\Controllers;

use App\Actions\Events\SetParticipation;
use App\Enums\ParticipationStatus;
use App\Http\Requests\Events\SetParticipantStatusRequest;
use App\Http\Requests\Events\UpdateOwnParticipationRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class EventParticipationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Accept or decline the event for oneself (F-11).
     *
     * A decline that would release claims is rejected with the affected
     * items until the request carries confirm_release.
     */
    public function update(UpdateOwnParticipationRequest $request, Event $event, SetParticipation $setParticipation): RedirectResponse
    {
        $status = $request->enum('status', ParticipationStatus::class);

        $setParticipation->handle($event, $request->user(), $status, $request->user(), $request->boolean('confirm_release'));

        $message = $status === ParticipationStatus::Accepted ? 'You accepted the event.' : 'You declined the event.';

        return back()->with('success', $message);
    }

    /**
     * Set the participation status for another member; their claims always stay (F-11).
     */
    public function updateFor(SetParticipantStatusRequest $request, Event $event, User $member, SetParticipation $setParticipation): RedirectResponse
    {
        $setParticipation->handle($event, $member, $request->enum('status', ParticipationStatus::class), $request->user());

        return back()->with('success', 'Participation updated.');
    }

    /**
     * Remove a participant's status; their claims stay (F-11).
     */
    public function destroy(Event $event, User $member): RedirectResponse
    {
        $this->authorize('removeParticipant', $event);

        $event->participations()->where('user_id', $member->getKey())->delete();

        return back()->with('success', 'Participant removed.');
    }
}

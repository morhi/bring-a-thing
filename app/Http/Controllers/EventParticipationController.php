<?php

namespace App\Http\Controllers;

use App\Actions\Events\SetParticipation;
use App\Actions\Events\WithdrawFromEvent;
use App\Enums\ParticipationStatus;
use App\Events\DashboardChanged;
use App\Events\ParticipationChanged;
use App\Http\Requests\Events\SetParticipantStatusRequest;
use App\Http\Requests\Events\UpdateOwnParticipationRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        ParticipationChanged::dispatch($event->id, $request->user()->id);
        DashboardChanged::forItem($event);

        $message = $status === ParticipationStatus::Accepted ? __('ui.event.acceptedToast') : __('ui.event.declinedToast');

        return back()->with('success', $message);
    }

    /**
     * Set the participation status for another member; their claims always stay (F-11).
     */
    public function updateFor(SetParticipantStatusRequest $request, Event $event, User $member, SetParticipation $setParticipation): RedirectResponse
    {
        $setParticipation->handle($event, $member, $request->enum('status', ParticipationStatus::class), $request->user());

        ParticipationChanged::dispatch($event->id, $member->id);
        DashboardChanged::forItem($event);

        return back()->with('success', __('ui.event.statusChanged', ['name' => $member->name ?? $member->email]));
    }

    /**
     * Withdraw from the event: the own participation goes and the own claims are released (F-11, F-22).
     */
    public function withdraw(Request $request, Event $event, WithdrawFromEvent $withdrawFromEvent): RedirectResponse
    {
        $this->authorize('withdraw', $event);

        $withdrawFromEvent->handle($event, $request->user());

        ParticipationChanged::dispatch($event->id, $request->user()->id);
        DashboardChanged::forItem($event);

        return to_route('dashboard')->with('success', __('ui.event.withdrawn', ['event' => $event->title]));
    }

    /**
     * Remove a participant's status; their claims stay (F-11).
     */
    public function destroy(Event $event, User $member): RedirectResponse
    {
        $this->authorize('removeParticipant', $event);

        $event->participations()->where('user_id', $member->getKey())->delete();

        ParticipationChanged::dispatch($event->id, $member->id);
        DashboardChanged::forItem($event);

        return back()->with('success', __('ui.event.removed', ['name' => $member->name ?? $member->email]));
    }
}

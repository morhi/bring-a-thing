<?php

namespace App\Http\Controllers;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\UpdateEvent;
use App\Actions\Notifications\SendInAppNotification;
use App\Data\EventFormData;
use App\Data\EventShowData;
use App\Events\DashboardChanged;
use App\Events\EventUpdated;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Event;
use App\Models\Group;
use App\Notifications\EventCreated;
use App\Notifications\EventMoved;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the "New event" page for the group, or for a sub-event with ?parent_event_id (F-10, F-12).
     */
    public function create(Request $request, Group $group): Response
    {
        $this->authorize('createForGroup', [Event::class, $group]);

        $parent = null;

        if ($request->filled('parent_event_id')) {
            $parent = Event::query()->where('group_id', $group->getKey())->findOrFail((int) $request->query('parent_event_id'));
            $this->authorize('createSubEvent', $parent);
        }

        return Inertia::render('Events/Create', [
            'form' => EventFormData::forGroup($group, parent: $parent),
        ]);
    }

    /**
     * Show the "New event" page with a select over the user's groups, for creating from outside a group (F-10).
     *
     * With ?parent_event_id it is the "New event" page for a sub-event of a standalone event (F-29).
     */
    public function createWithoutGroup(Request $request): Response
    {
        if ($request->filled('parent_event_id')) {
            $parent = Event::query()->whereNull('group_id')->findOrFail((int) $request->query('parent_event_id'));
            $this->authorize('createSubEvent', $parent);

            return Inertia::render('Events/Create', [
                'form' => EventFormData::forGroup(null, parent: $parent),
            ]);
        }

        return Inertia::render('Events/Create', [
            'form' => EventFormData::forCreate($request->user()),
        ]);
    }

    /**
     * Create an event directly and ask every group member to accept or decline.
     */
    public function store(StoreEventRequest $request, Group $group, CreateEvent $createEvent, SendInAppNotification $sendNotification): RedirectResponse
    {
        return $this->createEvent($request, $group, $createEvent, $sendNotification);
    }

    /**
     * Create an event in the group picked by group_id, or standalone without one (F-10, F-29).
     */
    public function storeAny(StoreEventRequest $request, CreateEvent $createEvent, SendInAppNotification $sendNotification): RedirectResponse
    {
        return $this->createEvent($request, $request->group(), $createEvent, $sendNotification);
    }

    /**
     * Create the event, refresh the dashboards and notify the people asked to accept or decline.
     */
    private function createEvent(StoreEventRequest $request, ?Group $group, CreateEvent $createEvent, SendInAppNotification $sendNotification): RedirectResponse
    {
        $event = $createEvent->handle($group, $request->user(), $request->eventAttributes(), $request->parentEvent());

        DashboardChanged::forItem($event);

        $sendNotification->handle(new EventCreated($event, $request->user()));

        if ($event->parent_event_id !== null) {
            EventUpdated::dispatch($event->parent_event_id);
        }

        return to_route('events.show', $event)->with('success', __('ui.newEvent.created'));
    }

    /**
     * Show the event: key details, participation, lists, follow-up polls and child events.
     */
    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        return Inertia::render('Events/Show', [
            'page' => EventShowData::forUser($event, $request->user()),
        ]);
    }

    /**
     * Show the "New event" page in edit mode to organizer, owner and admins.
     */
    public function edit(Request $request, Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Events/Create', [
            'form' => EventFormData::forGroup($event->group, $event, user: $request->user()),
        ]);
    }

    /**
     * Update the event; moving its start notifies the participants (F-23) and, when asked, resets acceptances to "pending".
     */
    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent, SendInAppNotification $sendNotification): RedirectResponse
    {
        $moved = $updateEvent->handle($event, $request->eventAttributes(), $request->resetsAcceptances());

        EventUpdated::dispatch($event->id);
        DashboardChanged::forItem($event);

        if ($moved) {
            $sendNotification->handle(new EventMoved($event, $request->user()));

            return to_route('events.show', $event)->with('success', __($request->resetsAcceptances() ? 'ui.event.edit.reset' : 'ui.event.edit.movedKept'));
        }

        return to_route('events.show', $event)->with('success', __('ui.event.edit.saved'));
    }

    /**
     * Delete the event with its lists, follow-up polls and child events.
     *
     * A standalone event returns to the dashboard, since it has no group page (F-29).
     */
    public function destroy(Event $event, DeleteEvent $deleteEvent): RedirectResponse
    {
        $this->authorize('delete', $event);

        $group = $event->group;
        $recipients = DashboardChanged::recipientsOf($event);
        $deleteEvent->handle($event);

        EventUpdated::dispatch($event->id, deleted: true);
        DashboardChanged::dispatch($event->group_id, $recipients);

        if ($event->parent_event_id !== null) {
            EventUpdated::dispatch($event->parent_event_id);
        }

        if ($group === null) {
            return to_route('dashboard')->with('success', __('ui.event.deleted'));
        }

        return to_route('groups.show', $group)->with('success', __('ui.event.deleted'));
    }
}

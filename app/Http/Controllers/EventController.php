<?php

namespace App\Http\Controllers;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\UpdateEvent;
use App\Data\EventFormData;
use App\Data\EventShowData;
use App\Events\EventUpdated;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the "New event" page for the group (F-10).
     */
    public function create(Group $group): Response
    {
        $this->authorize('createForGroup', [Event::class, $group]);

        return Inertia::render('Events/Create', [
            'form' => EventFormData::forGroup($group),
        ]);
    }

    /**
     * Create an event directly and ask every group member to accept or decline.
     */
    public function store(StoreEventRequest $request, Group $group, CreateEvent $createEvent): RedirectResponse
    {
        $event = $createEvent->handle($group, $request->user(), $request->eventAttributes());

        return to_route('events.show', $event)->with('success', 'Event created.');
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
    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Events/Create', [
            'form' => EventFormData::forGroup($event->group, $event),
        ]);
    }

    /**
     * Update the event; moving its start resets acceptances to "pending".
     */
    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent): RedirectResponse
    {
        $updateEvent->handle($event, $request->eventAttributes());

        EventUpdated::dispatch($event->id);

        return to_route('events.show', $event)->with('success', 'Event updated.');
    }

    /**
     * Delete the event with its lists, follow-up polls and child events.
     */
    public function destroy(Event $event, DeleteEvent $deleteEvent): RedirectResponse
    {
        $this->authorize('delete', $event);

        $group = $event->group;
        $deleteEvent->handle($event);

        EventUpdated::dispatch($event->id, deleted: true);

        if ($event->parent_event_id !== null) {
            EventUpdated::dispatch($event->parent_event_id);
        }

        return to_route('groups.show', $group)->with('success', 'Event deleted.');
    }
}

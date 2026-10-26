<?php

use App\Models\Event;
use App\Models\Poll;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel names carry the roster's internal id, not its public slug: they
// are never exposed as a URL, so bind on the raw id rather than the
// slug-based route key implicit binding would otherwise use.
Broadcast::channel('roster.{rosterId}', function (User $user, int $rosterId) {
    $roster = Roster::find($rosterId);

    return $roster !== null && $user->can('view', $roster);
});

Broadcast::channel('presence.roster.{rosterId}', function (User $user, int $rosterId) {
    $roster = Roster::find($rosterId);

    if ($roster === null || ! $user->can('view', $roster)) {
        return null;
    }

    return ['id' => $user->id, 'name' => $user->name ?? $user->email];
});

// Event pages: every member who may open the event hears participation,
// detail and comment changes. Payloads carry ids only, so a member without
// access to the thread learns nothing beyond what the event page shows (F-21).
Broadcast::channel('event.{eventId}', function (User $user, int $eventId) {
    $event = Event::find($eventId);

    return $event !== null && $user->can('view', $event);
});

Broadcast::channel('poll.{pollId}', function (User $user, int $pollId) {
    $poll = Poll::find($pollId);

    return $poll !== null && $user->can('view', $poll);
});

Broadcast::channel('presence.poll.{pollId}', function (User $user, int $pollId) {
    $poll = Poll::find($pollId);

    if ($poll === null || ! $user->can('view', $poll)) {
        return null;
    }

    return ['id' => $user->id, 'name' => $user->name ?? $user->email];
});

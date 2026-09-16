<?php

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

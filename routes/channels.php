<?php

use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('roster.{roster}', function (User $user, Roster $roster) {
    return $user->can('view', $roster);
});

Broadcast::channel('presence.roster.{roster}', function (User $user, Roster $roster) {
    if (! $user->can('view', $roster)) {
        return null;
    }

    return ['id' => $user->id, 'name' => $user->name ?? $user->email];
});

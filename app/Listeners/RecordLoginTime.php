<?php

namespace App\Listeners;

use App\Http\Middleware\EnsureRecentLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;

class RecordLoginTime
{
    /**
     * Remember when this session logged in, for the passkey re-authentication check.
     *
     * A session restored from the remember cookie also fires Login, but it
     * proves no fresh login, so a stolen cookie must not start the window.
     */
    public function handle(Login $event): void
    {
        if (! app()->bound('session.store') || Auth::guard($event->guard)->viaRemember()) {
            return;
        }

        session()->put(EnsureRecentLogin::SESSION_KEY, now()->timestamp);
    }
}

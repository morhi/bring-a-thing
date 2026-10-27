<?php

namespace App\Listeners;

use App\Http\Middleware\EnsureRecentLogin;
use Illuminate\Auth\Events\Login;

class RecordLoginTime
{
    /**
     * Remember when this session logged in, for the passkey re-authentication check.
     */
    public function handle(Login $event): void
    {
        if (! app()->bound('session.store')) {
            return;
        }

        session()->put(EnsureRecentLogin::SESSION_KEY, now()->timestamp);
    }
}

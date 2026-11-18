<?php

namespace App\Actions\Auth;

use App\Http\Middleware\EnsureRecentLogin;

class StartLoginWindow
{
    /**
     * Record that this session just proved a credential, for the passkey gate (F-02).
     *
     * Called where a credential is checked, not on the Login event: a session
     * restored from the remember cookie also fires that event and proves nothing.
     */
    public function handle(): void
    {
        session()->put(EnsureRecentLogin::SESSION_KEY, now()->timestamp);
    }
}

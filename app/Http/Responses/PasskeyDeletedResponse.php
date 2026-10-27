<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse as PasskeyDeletedResponseContract;

class PasskeyDeletedResponse implements PasskeyDeletedResponseContract
{
    /**
     * Send the user back to the settings with the toast message (F-02).
     */
    public function toResponse($request): RedirectResponse
    {
        return back()->with('success', __('ui.settings.passkeys.deleted'));
    }
}

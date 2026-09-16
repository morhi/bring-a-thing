<?php

namespace App\Actions\Auth;

use App\Mail\MagicLinkMail;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendMagicLink
{
    /**
     * Minutes a magic link stays valid after being issued.
     */
    public const EXPIRES_IN_MINUTES = 30;

    /**
     * Build a signed login link for the user and queue it for delivery.
     *
     * When a roster is passed (e.g. the one created during onboarding), the
     * link carries it along so the login can land the user there directly
     * instead of the dashboard.
     */
    public function handle(User $user, ?Roster $landOnRoster = null): void
    {
        $url = URL::temporarySignedRoute(
            'login.consume',
            now()->addMinutes(self::EXPIRES_IN_MINUTES),
            array_filter([
                'user' => $user->getKey(),
                'roster' => $landOnRoster?->getKey(),
            ]),
        );

        Mail::to($user)->queue(new MagicLinkMail($url, self::EXPIRES_IN_MINUTES));
    }
}

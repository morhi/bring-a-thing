<?php

namespace App\Actions\Auth;

use App\Mail\MagicLinkMail;
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
     */
    public function handle(User $user): void
    {
        $url = URL::temporarySignedRoute(
            'login.consume',
            now()->addMinutes(self::EXPIRES_IN_MINUTES),
            ['user' => $user->getKey()],
        );

        Mail::to($user)->queue(new MagicLinkMail($url, self::EXPIRES_IN_MINUTES));
    }
}

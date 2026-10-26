<?php

namespace App\Actions\Auth;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendEmailConfirmationLink
{
    /**
     * Send a signed confirmation link to the user's pending email (F-01).
     *
     * The link carries a hash of the pending address, so requesting another
     * address invalidates earlier links. It expires like a magic link.
     */
    public function handle(User $user): void
    {
        if ($user->pending_email === null) {
            return;
        }

        $url = URL::temporarySignedRoute(
            'settings.email.confirm',
            now()->addMinutes(SendMagicLink::EXPIRES_IN_MINUTES),
            [
                'user' => $user->getKey(),
                'hash' => self::hashFor($user->pending_email),
            ],
        );

        Mail::to($user->pending_email)
            ->locale($user->language ?? app()->getLocale())
            ->queue(new MagicLinkMail($url, SendMagicLink::EXPIRES_IN_MINUTES));
    }

    /**
     * Hash that binds a confirmation link to one pending address.
     */
    public static function hashFor(string $email): string
    {
        return hash('sha256', mb_strtolower($email));
    }
}

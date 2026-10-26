<?php

namespace App\Data;

use App\Actions\Auth\SendMagicLink;
use App\Enums\LoginLinkError;
use Spatie\LaravelData\Data;

class WelcomeData extends Data
{
    public function __construct(
        public ?LoginLinkError $login_link_error,
        public int $link_expires_in_minutes,
    ) {}

    /**
     * Build the start page state (F-01).
     *
     * login_link_error is set once after an expired or altered login link
     * bounced back here, so the form can offer a new link (section 8).
     */
    public static function make(?LoginLinkError $loginLinkError): self
    {
        return new self(
            login_link_error: $loginLinkError,
            link_expires_in_minutes: SendMagicLink::EXPIRES_IN_MINUTES,
        );
    }
}

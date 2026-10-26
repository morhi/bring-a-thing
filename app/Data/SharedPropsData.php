<?php

namespace App\Data;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class SharedPropsData extends Data
{
    public function __construct(
        public string $name,
        public SharedAuthData $auth,
        public Locale $locale,
        public SharedFlashData $flash,
        public SharedNotificationsData $notifications,
    ) {}

    /**
     * Build the props shared with every Inertia page for this request.
     */
    public static function fromRequest(Request $request): self
    {
        $user = $request->user();

        return new self(
            name: config('app.name'),
            auth: new SharedAuthData($user ? SharedUserData::fromUser($user) : null),
            locale: Locale::fromSupported(app()->getLocale()) ?? Locale::fallback(),
            flash: new SharedFlashData(
                success: $request->session()->get('success'),
                error: $request->session()->get('error'),
            ),
            notifications: SharedNotificationsData::forUser($user),
        );
    }
}

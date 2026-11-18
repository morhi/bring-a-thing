<?php

namespace App\Data;

use App\Enums\CredentialHint;
use App\Enums\Locale;
use App\Models\User;
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
        public ?SharedReverbData $reverb,
        public ?CredentialHint $credentialHint,
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
            reverb: SharedReverbData::fromConfig(),
            credentialHint: self::credentialHintFor($user),
        );
    }

    /**
     * Pick the hint to set a password or passkey (F-02); null when none applies.
     *
     * Cheap checks come first so dismissed and password accounts cost no passkey query.
     */
    private static function credentialHintFor(?User $user): ?CredentialHint
    {
        if (! $user || $user->credential_hint_dismissed_at || $user->password) {
            return null;
        }

        if ($user->passkeys()->exists()) {
            return null;
        }

        return $user->email ? CredentialHint::Email : CredentialHint::NoEmail;
    }
}

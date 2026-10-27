<?php

namespace App\Data;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

class SettingsData extends Data
{
    /**
     * @param  array<int, Locale>  $languages
     * @param  array<int, PasskeyData>  $passkeys
     */
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $pending_email,
        public bool $has_password,
        #[LiteralTypeScriptType('App.Enums.Locale[]')]
        public array $languages,
        public int $password_min_length,
        public array $passkeys,
    ) {}

    /**
     * Build the settings page for the account (F-01, F-02, F-24).
     *
     * pending_email is the address a name-only account asked to add and has
     * not confirmed yet. The password length follows the active password rule,
     * which is stricter in production.
     */
    public static function fromUser(User $user): self
    {
        return new self(
            name: $user->name,
            email: $user->email,
            pending_email: $user->pending_email,
            has_password: $user->password !== null,
            languages: Locale::cases(),
            password_min_length: (int) (Password::default()->appliedRules()['min'] ?? 8),
            passkeys: $user->passkeys()->oldest()->get()->map(PasskeyData::fromPasskey(...))->all(),
        );
    }
}

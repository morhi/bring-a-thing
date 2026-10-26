<?php

namespace App\Data;

use App\Enums\Locale;
use App\Models\User;
use Spatie\LaravelData\Data;

class SharedUserData extends Data
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $email,
        public ?Locale $language,
        public ?string $email_verified_at,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    /**
     * Build the shared auth user from the model, exposing only safe columns.
     */
    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            language: Locale::fromSupported($user->language),
            email_verified_at: $user->email_verified_at?->toJSON(),
            created_at: $user->created_at?->toJSON(),
            updated_at: $user->updated_at?->toJSON(),
        );
    }
}

<?php

namespace App\Data;

use Laravel\Passkeys\Passkey;
use Spatie\LaravelData\Data;

class PasskeyData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $created_at,
        public ?string $last_used_at,
    ) {}

    /**
     * Build one saved passkey for the settings list (F-02).
     */
    public static function fromPasskey(Passkey $passkey): self
    {
        return new self(
            id: $passkey->id,
            name: $passkey->name,
            created_at: $passkey->created_at?->toIso8601String() ?? '',
            last_used_at: $passkey->last_used_at?->toIso8601String(),
        );
    }
}

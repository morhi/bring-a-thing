<?php

namespace App\Data;

use App\Models\RosterItemClaim;
use Spatie\LaravelData\Data;

class RosterClaimData extends Data
{
    public function __construct(
        public ?int $user_id,
        public string $user_name,
        public ?float $quantity,
    ) {}

    /**
     * Build one claim on an item (F-15).
     *
     * The shared view leaves out the user ID, since that page has no
     * authentication boundary (F-19).
     */
    public static function fromClaim(RosterItemClaim $claim, bool $withUserId = true): self
    {
        return new self(
            user_id: $withUserId ? $claim->user_id : null,
            user_name: $claim->user->name ?? 'Someone',
            quantity: $claim->quantity === null ? null : (float) $claim->quantity,
        );
    }
}

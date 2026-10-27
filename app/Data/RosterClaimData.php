<?php

namespace App\Data;

use App\Models\RosterItemClaim;
use App\Models\User;
use Spatie\LaravelData\Data;

class RosterClaimData extends Data
{
    public function __construct(
        public ?int $user_id,
        public string $user_name,
        public ?float $quantity,
        public bool $is_own = false,
    ) {}

    /**
     * Build one claim on an item (F-15).
     *
     * The shared view leaves out the user ID, since that page has no
     * authentication boundary (F-19); is_own still lets a logged-in
     * visitor change or remove their own claim there.
     */
    public static function fromClaim(RosterItemClaim $claim, bool $withUserId = true, ?User $viewer = null): self
    {
        return new self(
            user_id: $withUserId ? $claim->user_id : null,
            user_name: $claim->user->name ?? 'Someone',
            quantity: $claim->quantity === null ? null : (float) $claim->quantity,
            is_own: $viewer !== null && $claim->user_id === $viewer->getKey(),
        );
    }
}

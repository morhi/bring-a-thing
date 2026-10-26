<?php

namespace App\Data;

use App\Models\RosterItemClaim;
use Spatie\LaravelData\Data;

class EventClaimData extends Data
{
    public function __construct(
        public string $item_name,
        public ?float $quantity,
        public ?string $unit,
        public string $roster_slug,
        public string $roster_title,
    ) {}

    /**
     * Build one claim that an own decline would release (F-11).
     */
    public static function fromClaim(RosterItemClaim $claim): self
    {
        return new self(
            item_name: $claim->rosterItem->name,
            quantity: $claim->quantity,
            unit: $claim->rosterItem->unit,
            roster_slug: $claim->rosterItem->roster->slug,
            roster_title: $claim->rosterItem->roster->title,
        );
    }
}

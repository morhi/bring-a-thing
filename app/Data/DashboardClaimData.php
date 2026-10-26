<?php

namespace App\Data;

use App\Models\RosterItemClaim;
use Spatie\LaravelData\Data;

class DashboardClaimData extends Data
{
    public function __construct(
        public string $roster_slug,
        public string $roster_title,
        public string $item_name,
        public ?float $quantity,
        public ?string $unit,
        public ?string $date,
    ) {}

    /**
     * Build one of the person's claims on an upcoming list (F-22).
     *
     * Expects rosterItem.roster and, for event lists, the event to be loaded.
     */
    public static function fromClaim(RosterItemClaim $claim): self
    {
        $item = $claim->rosterItem;
        $roster = $item->roster;
        $date = $item->date ?? $roster->date ?? $roster->event?->date;

        return new self(
            roster_slug: $roster->slug,
            roster_title: $roster->title,
            item_name: $item->name,
            quantity: $claim->quantity === null ? null : (float) $claim->quantity,
            unit: $item->unit,
            date: $date?->format('Y-m-d'),
        );
    }
}

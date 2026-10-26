<?php

namespace App\Data;

use App\Models\Roster;
use App\Models\RosterItem;
use Spatie\LaravelData\Data;

class GroupListData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $date,
        public int $item_count,
        public int $claimed_count,
    ) {}

    /**
     * Build a group list card with its claim progress (F-13, F-15).
     *
     * An item counts as claimed when a task has a claimant or a quantity is fully taken.
     */
    public static function fromRoster(Roster $roster): self
    {
        return new self(
            slug: $roster->slug,
            title: $roster->title,
            date: $roster->date?->format('Y-m-d'),
            item_count: $roster->items->count(),
            claimed_count: $roster->items
                ->filter(fn (RosterItem $item) => $item->quantity === null ? $item->claims->isNotEmpty() : $item->remainingQuantity() <= 0)
                ->count(),
        );
    }
}

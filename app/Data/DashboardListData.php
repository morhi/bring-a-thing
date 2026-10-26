<?php

namespace App\Data;

use App\Models\Roster;
use App\Models\RosterItem;
use Spatie\LaravelData\Data;

class DashboardListData extends Data
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $date,
        public int $claimed_count,
        public int $item_count,
        public bool $is_shared,
    ) {}

    /**
     * Build a standalone list card with its claim progress (F-22).
     *
     * Expects the items with their claims to be loaded; an item counts as
     * claimed once its quantity is covered, or at the first claim without one.
     */
    public static function fromRoster(Roster $roster): self
    {
        return new self(
            slug: $roster->slug,
            title: $roster->title,
            date: $roster->date?->format('Y-m-d'),
            claimed_count: $roster->items->filter(fn (RosterItem $item) => self::isClaimed($item))->count(),
            item_count: $roster->items->count(),
            is_shared: $roster->share_token !== null,
        );
    }

    /**
     * Whether the item's claims cover it.
     */
    private static function isClaimed(RosterItem $item): bool
    {
        if ($item->quantity === null) {
            return $item->claims->isNotEmpty();
        }

        return $item->remainingQuantity() <= 0;
    }
}

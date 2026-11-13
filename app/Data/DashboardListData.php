<?php

namespace App\Data;

use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Database\Eloquent\Builder;
use Spatie\LaravelData\Data;

class DashboardListData extends Data
{
    /**
     * The relations fromRoster() reads, to load them up front.
     */
    public const RELATIONS = ['items.claims', 'group', 'event'];

    public function __construct(
        public string $slug,
        public string $title,
        public ?string $date,
        public int $claimed_count,
        public int $item_count,
        public bool $is_shared,
        public bool $is_past,
        public ?string $group_name,
        public ?string $event_title,
        public ?string $event_slug,
    ) {}

    /**
     * Build a list row with its claim progress and placement (F-22, F-28, F-29).
     *
     * Expects RELATIONS to be loaded; an item counts as claimed once its
     * quantity is covered, or at the first claim without one. An event list
     * without its own date shows the event's date.
     */
    public static function fromRoster(Roster $roster): self
    {
        return new self(
            slug: $roster->slug,
            title: $roster->title,
            date: ($roster->date ?? $roster->event?->date)?->format('Y-m-d'),
            claimed_count: $roster->items->filter(fn (RosterItem $item) => self::isClaimed($item))->count(),
            item_count: $roster->items->count(),
            is_shared: $roster->share_token !== null,
            is_past: $roster->isPast() || ($roster->event?->isPast() ?? false),
            group_name: $roster->group?->name,
            event_title: $roster->event?->title,
            event_slug: $roster->event?->slug,
        );
    }

    /**
     * Order lists by date with undated ones last, newest first within a day.
     *
     * @param  Builder<Roster>  $query
     * @return Builder<Roster>
     */
    public static function ordered(Builder $query): Builder
    {
        return $query
            ->orderByRaw('date is null')
            ->orderBy('date')
            ->latest('id');
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

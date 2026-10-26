<?php

namespace App\Data;

use App\Models\CustomField;
use App\Models\Roster;
use App\Models\RosterItem;
use Spatie\LaravelData\Data;

class SharedRosterData extends Data
{
    /**
     * @param  array<int, CustomFieldData>  $custom_fields
     * @param  array<int, RosterItemData>  $items
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $date,
        public bool $is_past,
        public bool $can_add_items,
        public bool $is_all_taken,
        public array $custom_fields,
        public array $items,
    ) {}

    /**
     * Build the shared list for a share link visitor (F-19).
     *
     * Shows names but no email addresses and no user IDs.
     */
    public static function fromRoster(Roster $roster): self
    {
        $roster->load([
            'customFields',
            'items' => fn ($query) => $query
                ->with(['claims.user:id,name', 'customFieldValues', 'attendancePollOption'])
                ->orderBy('date'),
        ]);

        $items = $roster->items->map(fn (RosterItem $item) => RosterItemData::forShareLink($item));

        return new self(
            title: $roster->title,
            description: $roster->description,
            date: $roster->date?->format('Y-m-d'),
            is_past: $roster->isPast(),
            can_add_items: $roster->canBeAddedToByShareLinkVisitor(),
            is_all_taken: $items->isNotEmpty() && $items->every(fn (RosterItemData $item) => $item->is_fully_claimed),
            custom_fields: $roster->customFields->map(fn (CustomField $field) => CustomFieldData::fromField($field))->all(),
            items: $items->all(),
        );
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\RosterItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property int $roster_id
 * @property string $name
 * @property float|null $quantity
 * @property string|null $unit
 * @property string|null $notes
 * @property Carbon|null $date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'quantity', 'unit', 'notes', 'date'])]
class RosterItem extends Model
{
    /** @use HasFactory<RosterItemFactory> */
    use HasFactory, HasSlug;

    /**
     * The accessors to append to the model's array/JSON form.
     *
     * @var list<string>
     */
    protected $appends = ['is_past'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'date' => 'date',
        ];
    }

    /**
     * The roster this item belongs to.
     */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    /**
     * The claims members have made on this item.
     */
    public function claims(): HasMany
    {
        return $this->hasMany(RosterItemClaim::class);
    }

    /**
     * The custom field values set on this item.
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(RosterItemCustomFieldValue::class);
    }

    /**
     * The quantity still available to claim, or null when the item has no quantity.
     */
    public function remainingQuantity(): ?float
    {
        if ($this->quantity === null) {
            return null;
        }

        return (float) $this->quantity - (float) $this->claims->sum('quantity');
    }

    /**
     * Whether the item's effective date (its own, or its roster's) has passed.
     *
     * "Past" vs. "upcoming" has no explicit status field; it is always derived
     * from whichever date applies, per BRING_A_THING.md §3.
     */
    protected function isPast(): Attribute
    {
        return Attribute::get(function (): ?bool {
            $effectiveDate = $this->date ?? $this->roster->date;

            return $effectiveDate === null ? null : $effectiveDate->lt(Carbon::today());
        });
    }
}

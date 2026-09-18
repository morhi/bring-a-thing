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
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
 * @property int|null $attendance_poll_option_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'quantity', 'unit', 'notes', 'date', 'attendance_poll_option_id'])]
class RosterItem extends Model
{
    /** @use HasFactory<RosterItemFactory> */
    use HasFactory, HasSlug;

    /**
     * The accessors to append to the model's array/JSON form.
     *
     * @var list<string>
     */
    protected $appends = ['is_past', 'effective_attendance_poll_option'];

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
     * The attendance-poll day this item is linked to, if any.
     *
     * Overrides the roster's own link when set, mirroring the date-override
     * pattern used by isPast().
     */
    public function attendancePollOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class);
    }

    /**
     * The item's own comment thread.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * The attendance-poll day that gates claims on this item: its own link, or its roster's.
     *
     * Mirrors the item-overrides-roster fallback used by isPast().
     */
    protected function effectiveAttendancePollOption(): Attribute
    {
        return Attribute::get(fn (): ?PollOption => $this->attendance_poll_option_id !== null
            ? $this->attendancePollOption
            : $this->roster->attendancePollOption);
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

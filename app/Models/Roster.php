<?php

namespace App\Models;

use Database\Factories\RosterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $date
 * @property int $owner_id
 * @property int|null $group_id
 * @property bool $members_can_add_items
 * @property string|null $share_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'date', 'members_can_add_items'])]
class Roster extends Model
{
    /** @use HasFactory<RosterFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'members_can_add_items' => 'boolean',
        ];
    }

    /**
     * The user who created and owns the list.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The group the list is attached to, if any.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The items on this roster.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RosterItem::class)->chaperone();
    }

    /**
     * The custom fields defined on this roster.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * Whether the given user may add items to this roster.
     */
    public function canBeAddedToBy(User $user): bool
    {
        if ($this->owner_id === $user->getKey()) {
            return true;
        }

        if (! $this->members_can_add_items || $this->group_id === null) {
            return false;
        }

        return $this->group->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Turn on link sharing, generating a token first if none exists yet.
     */
    public function enableSharing(): string
    {
        if ($this->share_token === null) {
            $this->share_token = Str::random(32);
            $this->save();
        }

        return $this->share_token;
    }

    /**
     * Replace the share token with a new one, invalidating any previously shared link.
     */
    public function regenerateShareToken(): string
    {
        $this->share_token = Str::random(32);
        $this->save();

        return $this->share_token;
    }

    /**
     * Turn off link sharing.
     */
    public function disableSharing(): void
    {
        $this->share_token = null;
        $this->save();
    }

    /**
     * Clone this list's metadata, items, and custom fields into a new, independent list.
     *
     * The duplicate starts undated, since a cloned list (e.g. last week's
     * meal plan) is meant as a template for a new, not-yet-decided date.
     * Items are cloned undated too, for the same reason; claims are never
     * copied since they belong to the original occurrence.
     */
    public function duplicate(): self
    {
        $copy = static::query()->make([
            'title' => "{$this->title} (copy)",
            'description' => $this->description,
            'date' => null,
            'members_can_add_items' => $this->members_can_add_items,
        ]);
        $copy->owner_id = $this->owner_id;
        $copy->group_id = $this->group_id;
        $copy->save();

        $fieldIdMap = [];

        foreach ($this->customFields as $field) {
            $newField = $copy->customFields()->create(['name' => $field->name]);
            $fieldIdMap[$field->id] = $newField->id;
        }

        foreach ($this->items()->with('customFieldValues')->get() as $item) {
            $newItem = $copy->items()->create([
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'notes' => $item->notes,
                'date' => null,
            ]);

            foreach ($item->customFieldValues as $value) {
                $newItem->customFieldValues()->create([
                    'custom_field_id' => $fieldIdMap[$value->custom_field_id],
                    'value' => $value->value,
                ]);
            }
        }

        return $copy;
    }
}

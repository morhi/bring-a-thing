<?php

namespace App\Models;

use Database\Factories\RosterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $date
 * @property int $owner_id
 * @property int|null $group_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'date'])]
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
     * Clone this list's metadata into a new, independent list.
     *
     * The duplicate starts undated, since a cloned list (e.g. last week's
     * meal plan) is meant as a template for a new, not-yet-decided date.
     */
    public function duplicate(): self
    {
        $copy = static::query()->make([
            'title' => "{$this->title} (copy)",
            'description' => $this->description,
            'date' => null,
        ]);
        $copy->owner_id = $this->owner_id;
        $copy->group_id = $this->group_id;
        $copy->save();

        return $copy;
    }
}

<?php

namespace App\Models;

use Database\Factories\CustomFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $roster_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use HasFactory;

    /**
     * The roster this custom field is defined on.
     */
    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    /**
     * The per-item values recorded for this field.
     */
    public function values(): HasMany
    {
        return $this->hasMany(RosterItemCustomFieldValue::class);
    }
}

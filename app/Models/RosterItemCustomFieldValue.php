<?php

namespace App\Models;

use Database\Factories\RosterItemCustomFieldValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $roster_item_id
 * @property int $custom_field_id
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['custom_field_id', 'value'])]
class RosterItemCustomFieldValue extends Model
{
    /** @use HasFactory<RosterItemCustomFieldValueFactory> */
    use HasFactory;

    /**
     * The item this value belongs to.
     */
    public function rosterItem(): BelongsTo
    {
        return $this->belongsTo(RosterItem::class);
    }

    /**
     * The custom field this value is for.
     */
    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class);
    }
}

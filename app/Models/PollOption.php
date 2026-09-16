<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\PollOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property int $poll_id
 * @property Carbon $date
 * @property string|null $starts_at
 * @property string|null $ends_at
 * @property string|null $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'starts_at', 'ends_at', 'label'])]
class PollOption extends Model
{
    /** @use HasFactory<PollOptionFactory> */
    use HasFactory, HasSlug;

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
     * The poll this option belongs to.
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * The members' responses to this option.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }
}

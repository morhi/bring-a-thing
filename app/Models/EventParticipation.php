<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use Database\Factories\EventParticipationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $user_id
 * @property ParticipationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'status'])]
class EventParticipation extends Model
{
    /** @use HasFactory<EventParticipationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
        ];
    }

    /**
     * The event this participation belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The person taking part.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

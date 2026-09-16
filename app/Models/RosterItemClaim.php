<?php

namespace App\Models;

use Database\Factories\RosterItemClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $roster_item_id
 * @property int $user_id
 * @property float|null $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'quantity'])]
class RosterItemClaim extends Model
{
    /** @use HasFactory<RosterItemClaimFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    /**
     * The item this claim is on.
     */
    public function rosterItem(): BelongsTo
    {
        return $this->belongsTo(RosterItem::class);
    }

    /**
     * The member who made the claim.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

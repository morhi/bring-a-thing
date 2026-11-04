<?php

namespace App\Models;

use App\Enums\GroupJoinMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $accessible_type
 * @property int $accessible_id
 * @property GroupJoinMethod $joined_via
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'joined_via'])]
class AccessGrant extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_via' => GroupJoinMethod::class,
        ];
    }

    /**
     * The person who holds access to the entity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The event or poll the grant opens (F-26, F-27).
     */
    public function accessible(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Limit a grant query to the given sources, as [morph alias, id] pairs.
     *
     * @param  Builder<AccessGrant>  $query
     * @param  array<int, array{0: string, 1: int}>  $sources
     */
    public static function constrainToSources(Builder $query, array $sources): void
    {
        $query->where(function (Builder $query) use ($sources) {
            foreach ($sources as [$type, $id]) {
                $query->orWhere(fn (Builder $source) => $source->where('accessible_type', $type)->where('accessible_id', $id));
            }
        });
    }

    /**
     * The ids of the entities of one type the user holds a grant on, as a subquery.
     *
     * @return Builder<AccessGrant>
     */
    public static function idsFor(User $user, string $type): Builder
    {
        return self::query()
            ->select('accessible_id')
            ->where('user_id', $user->getKey())
            ->where('accessible_type', $type);
    }
}

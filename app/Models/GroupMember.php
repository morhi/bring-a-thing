<?php

namespace App\Models;

use App\Enums\GroupRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $group_id
 * @property int $user_id
 * @property GroupRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GroupMember extends Pivot
{
    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => GroupRole::class,
        ];
    }
}

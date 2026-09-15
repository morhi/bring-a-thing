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
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GroupMember extends Pivot
{
    protected $table = 'group_user';

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
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Whether the invited member has not yet logged in since being invited.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }
}

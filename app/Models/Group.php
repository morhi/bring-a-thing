<?php

namespace App\Models;

use App\Enums\GroupRole;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * The user who created and owns the group.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The users belonging to the group, including the owner.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(GroupMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Add a user to the group with the given role.
     */
    public function addMember(User $user, GroupRole $role): void
    {
        $this->members()->attach($user, ['role' => $role]);
    }
}

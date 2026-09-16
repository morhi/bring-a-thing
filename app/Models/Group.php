<?php

namespace App\Models;

use App\Enums\GroupRole;
use App\Models\Concerns\HasSlug;
use Carbon\CarbonInterface;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasSlug;

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
            ->withPivot('role', 'accepted_at')
            ->withTimestamps();
    }

    /**
     * The rosters attached to the group.
     */
    public function rosters(): HasMany
    {
        return $this->hasMany(Roster::class);
    }

    /**
     * The polls attached to the group.
     */
    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    /**
     * Whether the given user is the group's owner or an admin member.
     */
    public function isAtLeastAdmin(User $user): bool
    {
        if ($this->owner_id === $user->getKey()) {
            return true;
        }

        /** @var GroupMember|null $membership */
        $membership = $this->members()->whereKey($user->getKey())->first()?->pivot;

        return $membership !== null && $membership->isAtLeastAdmin();
    }

    /**
     * Add a user to the group with the given role.
     *
     * Leaving $acceptedAt null marks the membership as an invitation pending
     * the invitee's next login, per App\Listeners\MarkGroupInvitesAccepted.
     */
    public function addMember(User $user, GroupRole $role, ?CarbonInterface $acceptedAt = null): void
    {
        $this->members()->attach($user, ['role' => $role, 'accepted_at' => $acceptedAt]);
    }
}

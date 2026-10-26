<?php

namespace App\Models;

use App\Enums\GroupJoinMethod;
use App\Enums\GroupRole;
use App\Models\Concerns\HasSlug;
use Carbon\CarbonInterface;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $owner_id
 * @property string|null $invite_token
 * @property bool $invite_link_locked
 * @property bool $members_can_set_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'invite_link_locked', 'members_can_set_status'])]
#[Hidden(['invite_token'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invite_link_locked' => 'boolean',
            'members_can_set_status' => 'boolean',
        ];
    }

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
            ->withPivot('role', 'accepted_at', 'joined_via')
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
     * Whether the given user already belongs to the group.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    /**
     * Add a user to the group with the given role.
     *
     * Leaving $acceptedAt null marks the membership as an invitation pending
     * the invitee's next login, per App\Listeners\MarkGroupInvitesAccepted.
     */
    public function addMember(User $user, GroupRole $role, ?CarbonInterface $acceptedAt = null, ?GroupJoinMethod $joinedVia = null): void
    {
        $this->members()->attach($user, ['role' => $role, 'accepted_at' => $acceptedAt, 'joined_via' => $joinedVia]);
    }

    /**
     * Create the invite link token on first use and return it.
     */
    public function enableInviteLink(): string
    {
        if ($this->invite_token === null) {
            $this->invite_token = Str::random(32);
            $this->save();
        }

        return $this->invite_token;
    }

    /**
     * Replace the invite link token, so the previous link shows "Not found".
     */
    public function regenerateInviteToken(): string
    {
        $this->invite_token = Str::random(32);
        $this->save();

        return $this->invite_token;
    }

    /**
     * Revoke the invite link. The lock setting is kept for the next link.
     */
    public function revokeInviteLink(): void
    {
        $this->invite_token = null;
        $this->save();
    }

    /**
     * The public join URL of the invite link, or null while no link exists.
     */
    public function inviteUrl(): ?string
    {
        if ($this->invite_token === null) {
            return null;
        }

        return route('groups.join.show', $this->invite_token);
    }
}

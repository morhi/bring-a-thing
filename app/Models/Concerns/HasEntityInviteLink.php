<?php

namespace App\Models\Concerns;

use App\Models\AccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Invite link and access grants of an event or poll (F-26, F-27).
 *
 * Unlike a group link, an entity link has no lock: it is always open and
 * grants access to the entity and its sub-entities, never to the group.
 *
 * @property string|null $invite_token
 */
trait HasEntityInviteLink
{
    /**
     * The grant sources that open this entity, as [morph alias, id] pairs.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    abstract public function grantSources(): array;

    /**
     * The name of the route that shows this entity's join page.
     */
    abstract protected function inviteJoinRouteName(): string;

    /**
     * The grants given directly on this entity through its invite link.
     */
    public function accessGrants(): MorphMany
    {
        return $this->morphMany(AccessGrant::class, 'accessible');
    }

    /**
     * Whether the user holds a grant on this entity or on one it inherits access from.
     */
    public function grantsAccessTo(User $user): bool
    {
        return AccessGrant::query()
            ->where('user_id', $user->getKey())
            ->where(fn (Builder $query) => AccessGrant::constrainToSources($query, $this->grantSources()))
            ->exists();
    }

    /**
     * The people who reach this entity through a grant, oldest grant first.
     *
     * @return Collection<int, User>
     */
    public function grantHolders(): Collection
    {
        $sources = $this->grantSources();

        return User::query()
            ->whereHas('accessGrants', fn (Builder $query) => AccessGrant::constrainToSources($query, $sources))
            ->orderBy('id')
            ->get();
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
     * Replace the invite link token, so the previous link shows "Not found"; existing grants stay.
     */
    public function regenerateInviteToken(): string
    {
        $this->invite_token = Str::random(32);
        $this->save();

        return $this->invite_token;
    }

    /**
     * Revoke the invite link; people who already joined keep their access.
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

        return route($this->inviteJoinRouteName(), $this->invite_token);
    }
}

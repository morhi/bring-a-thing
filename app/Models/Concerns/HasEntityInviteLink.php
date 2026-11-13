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
     * Grants with their users that cover this entity's sources, set by preloadGrantHolders().
     *
     * @var Collection<int, AccessGrant>|null
     */
    protected ?Collection $preloadedGrants = null;

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
        return $this->holdersOf($this->grantSources());
    }

    /**
     * Load the grants of many entities in one query, so grantHolders() needs none per entity.
     *
     * Lists such as the group page resolve the guests of every poll; this
     * keeps their query count flat. Each entity's grant sources must be
     * loadable, so callers eager-load the relations grantSources() reads.
     *
     * @param  iterable<int, self>  $entities
     */
    public static function preloadGrantHolders(iterable $entities): void
    {
        $entities = collect($entities);
        $sources = $entities->flatMap(fn (self $entity) => $entity->grantSources())->unique(fn (array $source) => implode(':', $source))->values()->all();

        if ($sources === []) {
            return;
        }

        $grants = AccessGrant::query()
            ->where(fn (Builder $query) => AccessGrant::constrainToSources($query, $sources))
            ->with('user')
            ->get();

        $entities->each(fn (self $entity) => $entity->preloadedGrants = $grants);
    }

    /**
     * The people holding a grant on one of the given sources, oldest account first.
     *
     * @param  array<int, array{0: string, 1: int}>  $sources
     * @return Collection<int, User>
     */
    protected function holdersOf(array $sources): Collection
    {
        if ($this->preloadedGrants === null) {
            return User::query()
                ->whereHas('accessGrants', fn (Builder $query) => AccessGrant::constrainToSources($query, $sources))
                ->orderBy('id')
                ->get();
        }

        $keys = collect($sources)->map(fn (array $source) => implode(':', $source));

        return $this->preloadedGrants
            ->filter(fn (AccessGrant $grant) => $keys->contains($grant->accessible_type.':'.$grant->accessible_id))
            ->map(fn (AccessGrant $grant) => $grant->user)
            ->unique('id')
            ->sortBy('id')
            ->values();
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

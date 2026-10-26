<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\RosterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $date
 * @property int $owner_id
 * @property int|null $group_id
 * @property int|null $event_id
 * @property bool $members_can_add_items
 * @property string|null $share_token
 * @property int|null $attendance_poll_option_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'description', 'date', 'members_can_add_items', 'attendance_poll_option_id'])]
class Roster extends Model
{
    /** @use HasFactory<RosterFactory> */
    use HasFactory, HasSlug;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'members_can_add_items' => 'boolean',
        ];
    }

    /**
     * The user who created and owns the list.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The group the list is attached to, if any.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The event the list is attached to, if any.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The items on this roster.
     */
    public function items(): HasMany
    {
        return $this->hasMany(RosterItem::class)->chaperone();
    }

    /**
     * The custom fields defined on this roster.
     */
    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    /**
     * The attendance-poll day this roster is linked to, if any.
     *
     * Gates claim eligibility on this day's attendance (see RosterItem::attendancePollOption()),
     * unless an item overrides it with its own link.
     */
    public function attendancePollOption(): BelongsTo
    {
        return $this->belongsTo(PollOption::class);
    }

    /**
     * The list-level comment thread.
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * The group whose owner and admins manage this list: its own group, or its event's group.
     */
    public function managingGroup(): ?Group
    {
        if ($this->event_id !== null) {
            return $this->event->group;
        }

        return $this->group;
    }

    /**
     * Whether the user edits the list and manages its claims (F-13, F-15).
     *
     * List owner, group owner and admins; for an event list also the
     * event's organizer, who assigns the remaining items (section 6, flow 3).
     */
    public function isManagedBy(User $user): bool
    {
        if ($this->owner_id === $user->getKey()) {
            return true;
        }

        if ($this->event_id !== null) {
            return $this->event->isManagedBy($user);
        }

        return $this->group_id !== null && $this->group->isAtLeastAdmin($user);
    }

    /**
     * Whether the user holds a claim on any item of this list.
     */
    public function hasClaimBy(User $user): bool
    {
        return RosterItemClaim::query()
            ->where('user_id', $user->getKey())
            ->whereHas('rosterItem', fn (Builder $query) => $query->where('roster_id', $this->getKey()))
            ->exists();
    }

    /**
     * Whether the user sees the list by placement: owner, group member, event participant or person with a claim (F-13).
     *
     * Event lists are for participants ("accepted" or "pending") only;
     * whoever manages the list keeps seeing it regardless of their status.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isManagedBy($user)) {
            return true;
        }

        if ($this->event_id !== null) {
            return $this->event->hasParticipant($user);
        }

        if ($this->group_id !== null) {
            return $this->group->hasMember($user);
        }

        return $this->hasClaimBy($user);
    }

    /**
     * Whether the user's participation allows claiming: always, except on event lists where only "accepted" claims (F-15).
     */
    public function allowsClaimsBy(User $user): bool
    {
        if ($this->event_id === null) {
            return true;
        }

        return $this->event->hasAccepted($user);
    }

    /**
     * Whether the user sees the list but must accept the event before claiming (F-15).
     */
    public function needsAcceptanceBy(User $user): bool
    {
        if ($this->event_id === null) {
            return false;
        }

        return $this->event->statusOf($user) === ParticipationStatus::Pending;
    }

    /**
     * Whether a logged-in visitor of the share link is a group member kept out of this event list (F-13, F-19).
     *
     * The share link lets people outside the group take part. A member of
     * the event's group stays bound to the participation rules instead, so
     * the link is no way around a decline or a missing participation.
     */
    public function excludesShareLinkVisitor(User $user): bool
    {
        if ($this->event_id === null) {
            return false;
        }

        if ($this->isVisibleTo($user)) {
            return false;
        }

        return $this->event->group->hasMember($user);
    }

    /**
     * Whether a logged-in visitor of the share link may claim on this list (F-15, F-19).
     *
     * Outsiders claim through the link; members of an event's group need "accepted" like on the list page.
     */
    public function acceptsShareLinkClaimFrom(User $user): bool
    {
        if ($this->event_id === null || $this->isManagedBy($user)) {
            return true;
        }

        if (! $this->event->group->hasMember($user)) {
            return true;
        }

        return $this->allowsClaimsBy($user);
    }

    /**
     * Whether the given user may add items to this roster (F-14).
     *
     * Group owner and admins always can; with "Members can add items",
     * everyone who sees the list can as well.
     */
    public function canBeAddedToBy(User $user): bool
    {
        if ($this->owner_id === $user->getKey()) {
            return true;
        }

        $group = $this->managingGroup();

        if ($group === null) {
            return false;
        }

        if ($group->isAtLeastAdmin($user)) {
            return true;
        }

        if (! $this->members_can_add_items) {
            return false;
        }

        return $this->isVisibleTo($user);
    }

    /**
     * Whether the list's date has passed; a list has no status of its own (F-13).
     */
    public function isPast(): bool
    {
        return $this->date !== null && $this->date->lt(Carbon::today());
    }

    /**
     * Whether anyone using this roster's shared link may add things to it.
     *
     * Applies regardless of whether the roster is standalone or attached to
     * a group: the shared link is its own access grant, independent of
     * canBeAddedToBy()'s group-membership check on the authenticated route.
     * Only reachable via the shared-link routes, which already require a
     * valid share token, so sharing being enabled is implied here.
     */
    public function canBeAddedToByShareLinkVisitor(): bool
    {
        return $this->members_can_add_items;
    }

    /**
     * Turn on link sharing, generating a token first if none exists yet.
     */
    public function enableSharing(): string
    {
        if ($this->share_token === null) {
            $this->share_token = Str::random(32);
            $this->save();
        }

        return $this->share_token;
    }

    /**
     * Replace the share token with a new one, invalidating any previously shared link.
     */
    public function regenerateShareToken(): string
    {
        $this->share_token = Str::random(32);
        $this->save();

        return $this->share_token;
    }

    /**
     * Turn off link sharing.
     */
    public function disableSharing(): void
    {
        $this->share_token = null;
        $this->save();
    }

    /**
     * Clone this list's metadata, items, and custom fields into a new, independent list.
     *
     * The duplicate starts undated, since a cloned list (e.g. last week's
     * meal plan) is meant as a template for a new, not-yet-decided date.
     * Items are cloned undated too, for the same reason; claims are never
     * copied since they belong to the original occurrence.
     */
    public function duplicate(): self
    {
        $copy = static::query()->make([
            'title' => "{$this->title} (copy)",
            'description' => $this->description,
            'date' => null,
            'members_can_add_items' => $this->members_can_add_items,
        ]);
        $copy->owner_id = $this->owner_id;
        $copy->group_id = $this->group_id;
        $copy->event_id = $this->event_id;
        $copy->save();

        $fieldIdMap = [];

        foreach ($this->customFields as $field) {
            $newField = $copy->customFields()->create(['name' => $field->name]);
            $fieldIdMap[$field->id] = $newField->id;
        }

        foreach ($this->items()->with('customFieldValues')->get() as $item) {
            $newItem = $copy->items()->create([
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'notes' => $item->notes,
                'date' => null,
            ]);

            foreach ($item->customFieldValues as $value) {
                $newItem->customFieldValues()->create([
                    'custom_field_id' => $fieldIdMap[$value->custom_field_id],
                    'value' => $value->value,
                ]);
            }
        }

        return $copy;
    }
}

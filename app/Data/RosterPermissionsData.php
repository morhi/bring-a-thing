<?php

namespace App\Data;

use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Spatie\LaravelData\Data;

class RosterPermissionsData extends Data
{
    public function __construct(
        public bool $can_edit,
        public bool $can_delete,
        public bool $can_duplicate,
        public bool $can_add_items,
        public bool $can_edit_items,
        public bool $can_claim,
        public bool $needs_acceptance,
        public bool $can_manage_claims,
    ) {}

    /**
     * Resolve which list actions the user may see (F-13 to F-15, F-17).
     *
     * can_edit_items mirrors RosterItemPolicy update and delete, which hold for every item of the list alike (F-14).
     * needs_acceptance replaces the claim action with the note to accept
     * the event first, for pending participants of an event list.
     */
    public static function forUser(Roster $roster, User $user): self
    {
        return new self(
            can_edit: $user->can('update', $roster),
            can_delete: $user->can('delete', $roster),
            can_duplicate: $user->can('duplicate', $roster),
            can_add_items: $user->can('create', [RosterItem::class, $roster]),
            can_edit_items: $roster->isManagedBy($user),
            can_claim: $roster->allowsClaimsBy($user),
            needs_acceptance: $roster->needsAcceptanceBy($user),
            can_manage_claims: $roster->isManagedBy($user),
        );
    }
}

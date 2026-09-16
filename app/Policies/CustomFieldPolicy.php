<?php

namespace App\Policies;

use App\Models\CustomField;
use App\Models\Roster;
use App\Models\User;

class CustomFieldPolicy
{
    /**
     * Determine whether the user can define a custom field on the given roster.
     */
    public function create(User $user, Roster $roster): bool
    {
        return $roster->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can update the custom field.
     */
    public function update(User $user, CustomField $customField): bool
    {
        return $customField->roster->owner_id === $user->getKey();
    }

    /**
     * Determine whether the user can delete the custom field.
     */
    public function delete(User $user, CustomField $customField): bool
    {
        return $customField->roster->owner_id === $user->getKey();
    }
}

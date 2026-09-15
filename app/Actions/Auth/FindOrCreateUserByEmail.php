<?php

namespace App\Actions\Auth;

use App\Models\User;

class FindOrCreateUserByEmail
{
    /**
     * Find a user by email, or create a shadow account for it.
     *
     * Used by both self-registration and invite flows, so inviting the same
     * email twice reuses the existing user instead of duplicating it. A
     * provided name backfills an existing user that still has none, but
     * never overwrites a name the user already set for themselves.
     */
    public function handle(string $email, ?string $name = null): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if (! $user->name && $name) {
                $user->update(['name' => $name]);
            }

            return $user;
        }

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => null,
        ]);
    }
}

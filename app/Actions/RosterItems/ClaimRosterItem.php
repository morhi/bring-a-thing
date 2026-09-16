<?php

namespace App\Actions\RosterItems;

use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClaimRosterItem
{
    /**
     * Create or update the given user's claim on an item.
     *
     * Quantity-less items only accept one exclusive claimant; quantified
     * items accept split claims as long as the total never exceeds the
     * item's quantity.
     */
    public function handle(RosterItem $item, User $user, ?float $quantity): RosterItemClaim
    {
        $existing = $item->claims()->firstWhere('user_id', $user->getKey());

        if ($item->quantity === null) {
            $otherClaim = $item->claims()->where('user_id', '!=', $user->getKey())->exists();

            if ($otherClaim) {
                throw ValidationException::withMessages([
                    'quantity' => 'This item has already been claimed.',
                ]);
            }

            return $item->claims()->updateOrCreate(['user_id' => $user->getKey()], ['quantity' => null]);
        }

        $othersTotal = (float) $item->claims()
            ->where('user_id', '!=', $user->getKey())
            ->sum('quantity');

        if ($othersTotal + $quantity > (float) $item->quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'That quantity exceeds what is left to claim.',
            ]);
        }

        return $item->claims()->updateOrCreate(['user_id' => $user->getKey()], ['quantity' => $quantity]);
    }
}

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
     * item's quantity (F-14, F-15). Attendance never blocks a claim (F-18).
     */
    public function handle(RosterItem $item, User $user, ?float $quantity): RosterItemClaim
    {
        if ($item->quantity === null) {
            $otherClaim = $item->claims()->where('user_id', '!=', $user->getKey())->exists();

            if ($otherClaim) {
                throw ValidationException::withMessages([
                    'quantity' => 'This item has already been claimed.',
                ]);
            }

            return $item->claims()->updateOrCreate(['user_id' => $user->getKey()], ['quantity' => null]);
        }

        if ($quantity === null) {
            throw ValidationException::withMessages([
                'quantity' => 'Enter how many you are taking on.',
            ]);
        }

        $remaining = round((float) $item->quantity - (float) $item->claims()
            ->where('user_id', '!=', $user->getKey())
            ->sum('quantity'), 2);

        if (round($quantity, 2) > $remaining) {
            throw ValidationException::withMessages([
                'quantity' => $this->remainingMessage($remaining, $item->unit),
            ]);
        }

        return $item->claims()->updateOrCreate(['user_id' => $user->getKey()], ['quantity' => $quantity]);
    }

    /**
     * Phrase the rejection of a claim above the remaining quantity, naming what is left (section 8).
     */
    private function remainingMessage(float $remaining, ?string $unit): string
    {
        if ($remaining <= 0) {
            return 'Nothing is left to claim.';
        }

        $amount = rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.');

        return trim("Only {$amount} {$unit}").' left to claim.';
    }
}

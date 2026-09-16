<?php

namespace Database\Factories;

use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterItemClaim>
 */
class RosterItemClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roster_item_id' => RosterItem::factory(),
            'user_id' => User::factory(),
            'quantity' => null,
        ];
    }
}

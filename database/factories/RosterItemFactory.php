<?php

namespace Database\Factories;

use App\Models\Roster;
use App\Models\RosterItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterItem>
 */
class RosterItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roster_id' => Roster::factory(),
            'name' => $this->faker->words(2, true),
            'quantity' => null,
            'unit' => null,
            'notes' => null,
            'date' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Roster;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Roster>
 */
class RosterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'date' => null,
            'owner_id' => User::factory(),
            'group_id' => null,
        ];
    }
}

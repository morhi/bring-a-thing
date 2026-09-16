<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\Roster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
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
            'name' => $this->faker->word(),
        ];
    }
}

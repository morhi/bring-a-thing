<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'organizer_id' => User::factory(),
            'title' => $this->faker->words(3, true),
            'description' => null,
            'location' => null,
            'date' => $this->faker->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'starts_at' => null,
            'end_date' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Place the event's start in the past (F-10).
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->subWeek()->format('Y-m-d'),
        ]);
    }
}

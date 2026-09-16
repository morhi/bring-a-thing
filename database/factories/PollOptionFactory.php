<?php

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollOption>
 */
class PollOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'date' => $this->faker->dateTimeBetween('+1 day', '+1 month')->format('Y-m-d'),
            'starts_at' => null,
            'ends_at' => null,
            'label' => null,
        ];
    }
}

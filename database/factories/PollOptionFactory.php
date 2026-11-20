<?php

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollOption;
use App\Support\WallClock;
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

    /**
     * Make the option a time slot between Europe/Zurich wall-clock times on the given day (section 9).
     */
    public function slot(string $date, string $startTime, string $endTime): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => null,
            ...PollOption::scheduleFrom(['date' => $date, 'starts_at' => $startTime, 'ends_at' => $endTime], WallClock::DEFAULT_ZONE),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\WallClock;
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
            'end_date' => fn (array $attributes) => $attributes['date'],
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

    /**
     * Start the event at a Europe/Zurich wall-clock time on the given day, optionally ending the same day (section 9).
     */
    public function at(string $date, string $time, ?string $endTime = null): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => null,
            'starts_at' => WallClock::instant($date, $time, WallClock::DEFAULT_ZONE),
            'end_date' => $endTime === null ? $date : null,
            'ends_at' => $endTime === null ? null : WallClock::instant($date, $endTime, WallClock::DEFAULT_ZONE),
        ]);
    }
}

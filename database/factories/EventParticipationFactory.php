<?php

namespace Database\Factories;

use App\Enums\ParticipationStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventParticipation>
 */
class EventParticipationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => ParticipationStatus::Pending,
        ];
    }

    /**
     * Mark the participation as accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ParticipationStatus::Accepted]);
    }

    /**
     * Mark the participation as declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ParticipationStatus::Declined]);
    }
}

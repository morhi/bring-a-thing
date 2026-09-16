<?php

namespace Database\Factories;

use App\Enums\PollGranularity;
use App\Enums\PollType;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
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
            'type' => PollType::DateFinder,
            'granularity' => PollGranularity::Day,
            'organizer_id' => User::factory(),
            'group_id' => Group::factory(),
        ];
    }
}

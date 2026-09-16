<?php

namespace Database\Factories;

use App\Enums\PollResponseStatus;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollResponse>
 */
class PollResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_option_id' => PollOption::factory(),
            'user_id' => User::factory(),
            'status' => PollResponseStatus::Yes,
        ];
    }
}

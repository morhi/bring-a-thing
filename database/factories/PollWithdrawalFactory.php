<?php

namespace Database\Factories;

use App\Models\Poll;
use App\Models\PollWithdrawal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollWithdrawal>
 */
class PollWithdrawalFactory extends Factory
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
            'user_id' => User::factory(),
        ];
    }
}

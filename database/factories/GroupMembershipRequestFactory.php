<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMembershipRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMembershipRequest>
 */
class GroupMembershipRequestFactory extends Factory
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
            'user_id' => User::factory(),
        ];
    }
}

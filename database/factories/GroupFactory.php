<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Give the group an invite link, open by default or closed when locked (F-04).
     */
    public function withInviteLink(bool $locked = false): static
    {
        return $this->state(fn (array $attributes) => [
            'invite_token' => Str::random(32),
            'invite_link_locked' => $locked,
        ]);
    }
}

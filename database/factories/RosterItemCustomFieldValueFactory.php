<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\RosterItem;
use App\Models\RosterItemCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterItemCustomFieldValue>
 */
class RosterItemCustomFieldValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roster_item_id' => RosterItem::factory(),
            'custom_field_id' => CustomField::factory(),
            'value' => $this->faker->word(),
        ];
    }
}

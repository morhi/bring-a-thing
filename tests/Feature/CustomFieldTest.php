<?php

use App\Models\CustomField;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

it('allows the roster owner to define a custom field', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)->post(route('rosters.custom-fields.store', $roster), [
        'name' => 'Allergens',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('custom_fields', ['roster_id' => $roster->id, 'name' => 'Allergens']);
});

it('forbids a non-owner from defining a custom field', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($outsider)->post(route('rosters.custom-fields.store', $roster), [
        'name' => 'Allergens',
    ]);

    $response->assertForbidden();
});

it('rejects a duplicate custom field name on the same roster', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    CustomField::factory()->create(['roster_id' => $roster->id, 'name' => 'Allergens']);

    $response = $this->actingAs($owner)->post(route('rosters.custom-fields.store', $roster), [
        'name' => 'Allergens',
    ]);

    $response->assertSessionHasErrors('name');
});

it('allows the owner to rename a custom field', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $field = CustomField::factory()->create(['roster_id' => $roster->id, 'name' => 'Allergens']);

    $response = $this->actingAs($owner)->patch(route('rosters.custom-fields.update', [$roster, $field]), [
        'name' => 'Dietary needs',
    ]);

    $response->assertRedirect();
    expect($field->fresh()->name)->toBe('Dietary needs');
});

it('allows the owner to delete a custom field, cascading its values', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $field = CustomField::factory()->create(['roster_id' => $roster->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $value = $item->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'x']);

    $response = $this->actingAs($owner)->delete(route('rosters.custom-fields.destroy', [$roster, $field]));

    $response->assertRedirect();
    $this->assertModelMissing($field);
    $this->assertModelMissing($value);
});

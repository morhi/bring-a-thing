<?php

use App\Enums\GroupRole;
use App\Events\RosterItemDeleted;
use App\Events\RosterItemSaved;
use App\Models\CustomField;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('allows the roster owner to add an item', function () {
    Event::fake([RosterItemSaved::class]);
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)->post(route('rosters.items.store', $roster), [
        'name' => 'Chairs',
        'quantity' => 10,
        'unit' => 'pieces',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Thing added.');
    $item = RosterItem::query()->where('name', 'Chairs')->firstOrFail();
    expect($item->roster_id)->toBe($roster->id);
    Event::assertDispatched(RosterItemSaved::class, fn ($event) => $event->item->is($item));
});

it('forbids a group member without add-item permission from adding an item', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $response = $this->actingAs($member)->post(route('rosters.items.store', $roster), ['name' => 'Chairs']);

    $response->assertForbidden();
});

it('allows a group admin to add an item even when the roster forbids it for regular members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $roster = Roster::factory()->create([
        'owner_id' => $owner->id,
        'group_id' => $group->id,
        'members_can_add_items' => false,
    ]);

    $response = $this->actingAs($admin)->post(route('rosters.items.store', $roster), ['name' => 'Chairs']);

    $response->assertRedirect();
    expect(RosterItem::query()->where('name', 'Chairs')->exists())->toBeTrue();
});

it('allows a group admin to update the roster settings', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($admin, GroupRole::Admin, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $response = $this->actingAs($admin)->patch(route('rosters.update', $roster), ['title' => 'Updated title']);

    $response->assertRedirect();
    expect($roster->fresh()->title)->toBe('Updated title');
});

it('allows a group member to add an item when the roster permits it', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $roster = Roster::factory()->create([
        'owner_id' => $owner->id,
        'group_id' => $group->id,
        'members_can_add_items' => true,
    ]);

    $response = $this->actingAs($member)->post(route('rosters.items.store', $roster), ['name' => 'Chairs']);

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Chairs']);
});

it('forbids a non-owner from updating or deleting an item', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Chairs']);

    $this->actingAs($outsider)
        ->patch(route('rosters.items.update', [$roster, $item]), ['name' => 'Tables'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->delete(route('rosters.items.destroy', [$roster, $item]))
        ->assertForbidden();
});

it('allows the owner to delete an item and broadcasts the deletion', function () {
    Event::fake([RosterItemDeleted::class]);
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $response = $this->actingAs($owner)->delete(route('rosters.items.destroy', [$roster, $item]));

    $response->assertRedirect();
    $this->assertModelMissing($item);
    Event::assertDispatched(RosterItemDeleted::class, fn ($event) => $event->itemId === $item->id && $event->rosterId === $roster->id);
});

it('saves custom field values for an item', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $field = CustomField::factory()->create(['roster_id' => $roster->id, 'name' => 'Allergens']);

    $response = $this->actingAs($owner)->post(route('rosters.items.store', $roster), [
        'name' => 'Cake',
        'custom_fields' => [$field->id => 'Nuts'],
    ]);

    $response->assertRedirect();
    $item = RosterItem::query()->where('name', 'Cake')->firstOrFail();
    expect($item->customFieldValues()->where('custom_field_id', $field->id)->first()->value)->toBe('Nuts');
});

it('rejects a custom field value keyed to a field from a different roster', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $otherRoster = Roster::factory()->create(['owner_id' => $owner->id]);
    $foreignField = CustomField::factory()->create(['roster_id' => $otherRoster->id]);

    $response = $this->actingAs($owner)->post(route('rosters.items.store', $roster), [
        'name' => 'Cake',
        'custom_fields' => [$foreignField->id => 'Nuts'],
    ]);

    $response->assertSessionHasErrors('custom_fields');
});

it('derives past vs. upcoming from the item date, falling back to the roster date', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'date' => now()->subDay()->toDateString()]);
    $itemWithoutDate = RosterItem::factory()->create(['roster_id' => $roster->id, 'date' => null]);
    $itemWithFutureDate = RosterItem::factory()->create(['roster_id' => $roster->id, 'date' => now()->addDay()->toDateString()]);

    expect($itemWithoutDate->fresh()->is_past)->toBeTrue();
    expect($itemWithFutureDate->fresh()->is_past)->toBeFalse();
});

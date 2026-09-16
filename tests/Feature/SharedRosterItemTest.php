<?php

use App\Enums\GroupRole;
use App\Events\RosterItemSaved;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('exposes whether share-link visitors may add things', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();

    $response = $this->get(route('shared-rosters.show', $roster->share_token));

    $response->assertInertia(fn ($page) => $page->where('roster.can_add_items', true));
});

it('lets an authenticated visitor add a thing via the shared link when the setting is on', function () {
    Event::fake([RosterItemSaved::class]);
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();

    $response = $this->actingAs($visitor)->post(route('shared-rosters.items.store', $roster->share_token), [
        'name' => 'Napkins',
        'quantity' => 20,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Napkins']);
    Event::assertDispatched(RosterItemSaved::class);
});

it('forbids adding a thing via the shared link when the setting is off', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => false]);
    $roster->enableSharing();

    $response = $this->actingAs($visitor)->post(route('shared-rosters.items.store', $roster->share_token), [
        'name' => 'Napkins',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('roster_items', ['roster_id' => $roster->id, 'name' => 'Napkins']);
});

it('lets an authenticated non-member add a thing via the shared link for a group-attached roster', function () {
    Event::fake([RosterItemSaved::class]);
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $roster = Roster::factory()->create([
        'owner_id' => $owner->id,
        'group_id' => $group->id,
        'members_can_add_items' => true,
    ]);
    $roster->enableSharing();

    $response = $this->actingAs($visitor)->post(route('shared-rosters.items.store', $roster->share_token), [
        'name' => 'Napkins',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Napkins']);
});

it('sends an unauthenticated visitor to log in and stashes their return to the shared list', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();

    $response = $this->post(route('shared-rosters.items.store', $roster->share_token), [
        'name' => 'Napkins',
    ]);

    $response->assertRedirect(route('welcome'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('roster_items', ['roster_id' => $roster->id, 'name' => 'Napkins']);
});

<?php

use App\Enums\GroupRole;
use App\Events\RosterItemSaved;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('shows a shared list to a guest without requiring authentication', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'title' => 'Camping Trip']);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'name' => 'Tent']);

    $response = $this->get(route('shared-rosters.show', $roster->share_token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('roster.title', 'Camping Trip')
        ->where('roster.items.0.name', 'Tent')
    );
});

it('does not expose a claimer email address on the shared view', function () {
    $owner = User::factory()->create();
    $claimant = User::factory()->create(['name' => 'Claimant Name', 'email' => 'secret@example.com']);
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);
    $item->claims()->create(['user_id' => $claimant->id, 'quantity' => null]);

    $response = $this->get(route('shared-rosters.show', $roster->share_token));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('roster.items.0.claims.0.user_name', 'Claimant Name')
    );
    $response->assertDontSee('secret@example.com');
});

it('returns not found for an unknown share token', function () {
    $response = $this->get(route('shared-rosters.show', 'not-a-real-token'));

    $response->assertNotFound();
});

it('returns not found once sharing has been disabled', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $token = $roster->share_token;
    $roster->disableSharing();

    $response = $this->get(route('shared-rosters.show', $token));

    $response->assertNotFound();
});

it('redirects a member who already has full access to the normal roster page', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $roster->enableSharing();

    $response = $this->actingAs($member)->get(route('shared-rosters.show', $roster->share_token));

    $response->assertRedirect(route('rosters.show', $roster));
});

it('lets an already authenticated guest claim an item directly', function () {
    Event::fake([RosterItemSaved::class]);
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $response = $this->actingAs($claimant)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]));

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $claimant->id]);
    Event::assertDispatched(RosterItemSaved::class);
});

it('stashes a guest claim intent and sends an unauthenticated visitor to log in', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);

    $response = $this->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]), ['quantity' => 4]);

    $response->assertRedirect(route('welcome'));
    $response->assertSessionHas('pending_shared_claim', [
        'roster_token' => $roster->share_token,
        'item_id' => $item->id,
        'quantity' => 4.0,
    ]);
    $this->assertDatabaseMissing('roster_item_claims', ['roster_item_id' => $item->id]);
});

it('returns not found when the item does not belong to the shared roster', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $otherRoster = Roster::factory()->create(['owner_id' => $owner->id]);
    $item = RosterItem::factory()->create(['roster_id' => $otherRoster->id]);

    $response = $this->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]));

    $response->assertNotFound();
});

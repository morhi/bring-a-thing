<?php

use App\Enums\GroupRole;
use App\Events\RosterItemSaved;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Create a group-attached roster with the owner and the given members joined,
 * so every claimant in these tests can actually view the roster.
 *
 * @param  array<int, User>  $members
 */
function rosterWithMembers(User $owner, array $members, array $rosterAttributes = []): Roster
{
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    return Roster::factory()->create([...$rosterAttributes, 'owner_id' => $owner->id, 'group_id' => $group->id]);
}

it('claims an unquantified item exclusively', function () {
    Event::fake([RosterItemSaved::class]);
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = rosterWithMembers($owner, [$claimant]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $response = $this->actingAs($claimant)->post(route('rosters.items.claim.store', [$roster, $item]));

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $claimant->id]);
    Event::assertDispatched(RosterItemSaved::class);
});

it('blocks a second exclusive claim on an unquantified item', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $roster = rosterWithMembers($owner, [$first, $second]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $item->claims()->create(['user_id' => $first->id, 'quantity' => null]);

    $response = $this->actingAs($second)->post(route('rosters.items.claim.store', [$roster, $item]));

    $response->assertSessionHasErrors('quantity');
    expect($item->claims()->count())->toBe(1);
});

it('accepts a partial claim on a quantified item', function () {
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = rosterWithMembers($owner, [$claimant]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);

    $response = $this->actingAs($claimant)->post(route('rosters.items.claim.store', [$roster, $item]), [
        'quantity' => 4,
    ]);

    $response->assertRedirect();
    expect((float) $item->claims()->where('user_id', $claimant->id)->first()->quantity)->toBe(4.0);
});

it('accepts split claims from two members up to the full quantity', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $roster = rosterWithMembers($owner, [$first, $second]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);

    $this->actingAs($first)->post(route('rosters.items.claim.store', [$roster, $item]), ['quantity' => 6])->assertRedirect();
    $this->actingAs($second)->post(route('rosters.items.claim.store', [$roster, $item]), ['quantity' => 4])->assertRedirect();

    expect((float) $item->claims()->sum('quantity'))->toBe(10.0);
});

it('rejects a claim that would exceed the item quantity', function () {
    $owner = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $roster = rosterWithMembers($owner, [$first, $second]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);
    $item->claims()->create(['user_id' => $first->id, 'quantity' => 8]);

    $response = $this->actingAs($second)->post(route('rosters.items.claim.store', [$roster, $item]), [
        'quantity' => 5,
    ]);

    $response->assertSessionHasErrors('quantity');
    expect((float) $item->claims()->sum('quantity'))->toBe(8.0);
});

it('lets a member update their own claim quantity', function () {
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = rosterWithMembers($owner, [$claimant]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);
    $item->claims()->create(['user_id' => $claimant->id, 'quantity' => 3]);

    $response = $this->actingAs($claimant)->post(route('rosters.items.claim.store', [$roster, $item]), [
        'quantity' => 5,
    ]);

    $response->assertRedirect();
    expect((float) $item->claims()->where('user_id', $claimant->id)->first()->quantity)->toBe(5.0);
    expect($item->claims()->count())->toBe(1);
});

it('lets a member unclaim their own claim', function () {
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = rosterWithMembers($owner, [$claimant]);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10]);
    $item->claims()->create(['user_id' => $claimant->id, 'quantity' => 5]);

    $response = $this->actingAs($claimant)->delete(route('rosters.items.claim.destroy', [$roster, $item]));

    $response->assertRedirect();
    expect($item->claims()->where('user_id', $claimant->id)->exists())->toBeFalse();
});

it('forbids claiming an item on a roster the user cannot view', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = rosterWithMembers($owner, []);
    $item = RosterItem::factory()->create(['roster_id' => $roster->id]);

    $response = $this->actingAs($outsider)->post(route('rosters.items.claim.store', [$roster, $item]));

    $response->assertForbidden();
});

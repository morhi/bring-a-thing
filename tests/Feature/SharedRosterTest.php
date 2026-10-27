<?php

use App\Enums\GroupRole;
use App\Events\ClaimChanged;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => $this->withoutVite());

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
    Event::fake([ClaimChanged::class]);
    $owner = User::factory()->create();
    $claimant = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $response = $this->actingAs($claimant)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]));

    $response->assertRedirect();
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $claimant->id]);
    Event::assertDispatched(ClaimChanged::class);
});

it('stashes a guest claim intent and keeps an unauthenticated visitor on the shared list to log in', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 10, 'name' => 'Chairs']);

    $response = $this->from(route('shared-rosters.show', $roster->share_token))
        ->post(route('shared-rosters.items.claim.store', [$roster->share_token, $item]), ['quantity' => 4]);

    $response->assertRedirect(route('shared-rosters.show', $roster->share_token));
    $response->assertSessionHas('pending_shared_actions', [[
        'kind' => 'claim',
        'roster_token' => $roster->share_token,
        'item_id' => $item->id,
        'quantity' => 4.0,
    ]]);
    $this->assertDatabaseMissing('roster_item_claims', ['roster_item_id' => $item->id]);

    $this->get(route('shared-rosters.show', $roster->share_token))
        ->assertInertia(fn ($page) => $page
            ->where('roster.pending_actions', [['kind' => 'claim', 'name' => 'Chairs']])
        );
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

it('F-19: adds a guest item draft with its custom field values right after the magic link login', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $field = $roster->customFields()->create(['name' => 'Allergens']);

    $this->from(route('shared-rosters.show', $roster->share_token))
        ->post(route('shared-rosters.items.store', $roster->share_token), [
            'name' => 'Salad',
            'quantity' => 2,
            'unit' => 'bowls',
            'custom_fields' => [$field->id => 'Nuts'],
        ])
        ->assertRedirect(route('shared-rosters.show', $roster->share_token));

    $this->assertDatabaseMissing('roster_items', ['roster_id' => $roster->id, 'name' => 'Salad']);

    $guest = User::factory()->create();
    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]);

    $this->get($url)->assertRedirect(route('shared-rosters.show', $roster->share_token));

    $item = RosterItem::query()->where('roster_id', $roster->id)->where('name', 'Salad')->firstOrFail();
    expect((float) $item->quantity)->toBe(2.0)
        ->and($item->unit)->toBe('bowls')
        ->and($item->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('Nuts');
});

it('F-19: drops a pending guest item when the list stopped accepting additions before the login', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();

    $this->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Late Salad']);
    $roster->update(['members_can_add_items' => false]);

    $guest = User::factory()->create();
    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]);

    $this->get($url)->assertRedirect(route('shared-rosters.show', $roster->share_token));
    $this->assertDatabaseMissing('roster_items', ['name' => 'Late Salad']);
});

it('F-16: rejects a custom field of another list when adding through a share link', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $foreignField = Roster::factory()->create(['owner_id' => $owner->id])->customFields()->create(['name' => 'Color']);

    $this->actingAs(User::factory()->create())
        ->post(route('shared-rosters.items.store', $roster->share_token), [
            'name' => 'Cake',
            'custom_fields' => [$foreignField->id => 'Red'],
        ])
        ->assertSessionHasErrors('custom_fields');
});

it('F-15: marks the own claim on the shared view and lets a logged-in visitor remove it', function () {
    Event::fake([ClaimChanged::class]);
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    // A group list keeps an outside claimer on the shared view (F-13)
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 3]);
    $item->claims()->create(['user_id' => $visitor->id, 'quantity' => 3]);

    $this->actingAs($visitor)->get(route('shared-rosters.show', $roster->share_token))
        ->assertInertia(fn ($page) => $page
            ->where('roster.items.0.claims.0.is_own', true)
            ->where('roster.items.0.claims.0.user_id', null)
            ->where('roster.items.0.can_claim', true)
        );

    $this->actingAs($visitor)
        ->delete(route('shared-rosters.items.claim.destroy', [$roster->share_token, $item]))
        ->assertRedirect();

    $this->assertDatabaseMissing('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $visitor->id]);
    Event::assertDispatched(ClaimChanged::class);
});

it('F-19: does not let a guest remove claims through a share link', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);
    $item->claims()->create(['user_id' => $owner->id]);

    $this->delete(route('shared-rosters.items.claim.destroy', [$roster->share_token, $item]))->assertNotFound();
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id]);
});

/**
 * Log the given guest in through a magic link, as if they followed it in the same browser.
 */
function followMagicLink(User $guest): TestResponse
{
    return test()->get(URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $guest->id]));
}

it('F-19: completes a guest claim and a later item add after the login', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $tent = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null, 'name' => 'Tent']);
    $from = route('shared-rosters.show', $roster->share_token);

    $this->from($from)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $tent]));
    $this->from($from)->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Salad']);

    $this->get($from)->assertInertia(fn ($page) => $page->where('roster.pending_actions', [
        ['kind' => 'claim', 'name' => 'Tent'],
        ['kind' => 'add', 'name' => 'Salad'],
    ]));

    $guest = User::factory()->create();

    followMagicLink($guest)
        ->assertRedirect($from)
        ->assertSessionHas('success', 'Done after the login: Tent claimed, Salad added');

    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $tent->id, 'user_id' => $guest->id]);
    $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Salad']);
});

it('F-19: completes a guest item add and a later claim after the password login', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $chairs = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 6, 'name' => 'Chairs']);
    $from = route('shared-rosters.show', $roster->share_token);

    $this->from($from)->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Salad']);
    $this->from($from)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $chairs]), ['quantity' => 2]);

    $guest = User::factory()->create(['password' => 'secret-password']);

    $this->post(route('login.password'), [
        'email' => $guest->email,
        'password' => 'secret-password',
        'pending_claim_roster_token' => $roster->share_token,
    ])
        ->assertRedirect($from)
        ->assertSessionHas('success', 'Done after the login: Salad added, Chairs claimed');

    $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Salad']);
    expect((float) $chairs->claims()->where('user_id', $guest->id)->value('quantity'))->toBe(2.0);
});

it('F-19: completes two guest claims after the login and merges a repeated claim of the same item', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $chairs = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 6, 'name' => 'Chairs']);
    $tent = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null, 'name' => 'Tent']);
    $from = route('shared-rosters.show', $roster->share_token);

    $this->from($from)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $chairs]), ['quantity' => 2]);
    $this->from($from)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $tent]));
    $this->from($from)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $chairs]), ['quantity' => 3]);

    expect(session('pending_shared_actions'))->toHaveCount(2);

    $guest = User::factory()->create();

    followMagicLink($guest)
        ->assertRedirect($from)
        ->assertSessionHas('success', 'Done after the login: Chairs claimed, Tent claimed');

    expect((float) $chairs->claims()->where('user_id', $guest->id)->value('quantity'))->toBe(3.0);
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $tent->id, 'user_id' => $guest->id]);
});

it('F-19: still adds the guest item when the pending claim fails because the amount is gone', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'members_can_add_items' => true]);
    $roster->enableSharing();
    $chairs = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => 2, 'name' => 'Chairs']);
    $from = route('shared-rosters.show', $roster->share_token);

    $this->from($from)->post(route('shared-rosters.items.claim.store', [$roster->share_token, $chairs]), ['quantity' => 2]);
    $this->from($from)->post(route('shared-rosters.items.store', $roster->share_token), ['name' => 'Salad']);

    // Someone else takes the whole amount before the guest follows the link
    $chairs->claims()->create(['user_id' => $owner->id, 'quantity' => 2]);
    $guest = User::factory()->create();

    followMagicLink($guest)
        ->assertRedirect($from)
        ->assertSessionHas('success', 'Done after the login: Salad added')
        ->assertSessionHas('error', 'Your claim of Chairs did not go through: Nothing is left to claim.');

    $this->assertDatabaseMissing('roster_item_claims', ['roster_item_id' => $chairs->id, 'user_id' => $guest->id]);
    $this->assertDatabaseHas('roster_items', ['roster_id' => $roster->id, 'name' => 'Salad']);
    expect(session('pending_shared_actions'))->toBeNull();
});

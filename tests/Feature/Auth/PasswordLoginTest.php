<?php

use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;

it('logs in a user with a correct password', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects a login with an incorrect password', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertInvalid('email');
    $this->assertGuest();
});

it('rejects a password login for a user who has no password set', function () {
    $user = User::factory()->shadow()->create();

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'anything',
    ]);

    $response->assertInvalid('email');
    $this->assertGuest();
});

it('stashes and completes a pending shared-list claim submitted from the login dialog on a password login', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create(['password' => 'correct-password']);
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $response = $this->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'correct-password',
        'pending_claim_roster_token' => $roster->share_token,
        'pending_claim_item_id' => $item->id,
    ]);

    $response->assertRedirect(route('shared-rosters.show', $roster->share_token));
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $user->id]);
});

it('completes a pending shared-list claim started before a password login', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create(['password' => 'correct-password']);
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $item = RosterItem::factory()->create(['roster_id' => $roster->id, 'quantity' => null]);

    $response = $this->withSession(['pending_shared_claim' => [
        'roster_token' => $roster->share_token,
        'item_id' => $item->id,
        'quantity' => null,
    ]])->post(route('login.password'), [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertRedirect(route('shared-rosters.show', $roster->share_token));
    $this->assertDatabaseHas('roster_item_claims', ['roster_item_id' => $item->id, 'user_id' => $user->id]);
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('welcome'));
    $this->assertGuest();
});

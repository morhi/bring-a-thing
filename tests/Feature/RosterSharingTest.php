<?php

use App\Models\Roster;
use App\Models\User;

it('lets the owner enable sharing, generating a token', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($owner)->post(route('rosters.sharing.store', $roster));

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($roster->fresh()->share_token)->not->toBeNull();
});

it('forbids a non-owner from enabling sharing', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $response = $this->actingAs($outsider)->post(route('rosters.sharing.store', $roster));

    $response->assertForbidden();
    expect($roster->fresh()->share_token)->toBeNull();
});

it('lets the owner regenerate the share link, invalidating the old token', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();
    $originalToken = $roster->share_token;

    $response = $this->actingAs($owner)->post(route('rosters.sharing.regenerate', $roster));

    $response->assertRedirect();
    expect($roster->fresh()->share_token)->not->toBe($originalToken);
});

it('lets the owner disable sharing', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();

    $response = $this->actingAs($owner)->delete(route('rosters.sharing.destroy', $roster));

    $response->assertRedirect();
    expect($roster->fresh()->share_token)->toBeNull();
});

it('forbids a non-owner from disabling sharing', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);
    $roster->enableSharing();

    $response = $this->actingAs($outsider)->delete(route('rosters.sharing.destroy', $roster));

    $response->assertForbidden();
    expect($roster->fresh()->share_token)->not->toBeNull();
});

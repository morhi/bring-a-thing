<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

it('F-19: the list page gives the owner the share flag and the link only while sharing is on', function (bool $enabled) {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    if ($enabled) {
        $roster->enableSharing();
    }

    $this->actingAs($owner)->get(route('rosters.show', $roster))->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_share', true)
        ->where('page.roster.share_url', $enabled ? route('shared-rosters.show', $roster->fresh()->share_token) : null));
})->with([[true], [false]]);

it('F-19: the list page and the sharing routes refuse those who do not own the list', function (string $who) {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    if ($who === 'group member') {
        $group = Group::factory()->create(['owner_id' => $owner->id]);
        $group->addMember($owner, GroupRole::Owner, now());
        $group->addMember($viewer, GroupRole::Member, now());
        $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);
    } else {
        $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => null, 'event_id' => null]);
        RosterItem::factory()->create(['roster_id' => $roster->id])->claims()->create(['user_id' => $viewer->id, 'quantity' => null]);
    }

    $token = $roster->enableSharing();

    $this->actingAs($viewer)->get(route('rosters.show', $roster))->assertInertia(fn (Assert $page) => $page
        ->where('page.permissions.can_share', false)
        ->where('page.roster.share_url', null));
    $this->actingAs($viewer)->post(route('rosters.sharing.store', $roster))->assertForbidden();
    $this->actingAs($viewer)->post(route('rosters.sharing.regenerate', $roster))->assertForbidden();
    $this->actingAs($viewer)->delete(route('rosters.sharing.destroy', $roster))->assertForbidden();
    expect($roster->fresh()->share_token)->toBe($token);
})->with(['group member', 'claim holder on a standalone list']);

// Stays a feature test: Roster persists through the database, which the unit suite does not set up
it('F-19: enabling sharing twice keeps the token and regenerating changes it', function () {
    $roster = Roster::factory()->create();

    $token = $roster->enableSharing();

    expect($roster->enableSharing())->toBe($token);
    expect($roster->regenerateShareToken())->not->toBe($token);
});

<?php

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\Roster;
use App\Models\User;

beforeEach(function () {
    // The "null" broadcaster used in testing trivially authorizes every
    // channel; switch to the real driver so /broadcasting/auth actually
    // evaluates the channels.php callbacks, then re-register those
    // callbacks since they were bound to the "null" driver at boot.
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');
});

it('authorizes the roster owner on the roster private and presence channels', function () {
    $owner = User::factory()->create();
    $roster = Roster::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->postJson('/broadcasting/auth', ['channel_name' => 'private-roster.'.$roster->id, 'socket_id' => '1234.1234'])
        ->assertOk();

    $this->actingAs($owner)
        ->postJson('/broadcasting/auth', ['channel_name' => 'presence-presence.roster.'.$roster->id, 'socket_id' => '1234.1234'])
        ->assertOk();
});

it('denies a non-member on the roster private and presence channels', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $roster = Roster::factory()->create(['owner_id' => $owner->id, 'group_id' => $group->id]);

    $this->actingAs($outsider)
        ->postJson('/broadcasting/auth', ['channel_name' => 'private-roster.'.$roster->id, 'socket_id' => '1234.1234'])
        ->assertForbidden();

    $this->actingAs($outsider)
        ->postJson('/broadcasting/auth', ['channel_name' => 'presence-presence.roster.'.$roster->id, 'socket_id' => '1234.1234'])
        ->assertForbidden();
});

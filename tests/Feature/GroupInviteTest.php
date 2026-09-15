<?php

use App\Enums\GroupRole;
use App\Mail\MagicLinkMail;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('creates a shadow user, adds them as a member, and sends a magic link when inviting a new email', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);

    $response = $this->actingAs($owner)->post(route('groups.invite', $group), ['email' => 'invitee@example.com']);

    $response->assertRedirect();
    $invitee = User::query()->where('email', 'invitee@example.com')->firstOrFail();
    expect($invitee->name)->toBeNull();
    $pivot = $group->members()->whereKey($invitee->id)->first()?->pivot;
    expect($pivot->role)->toBe(GroupRole::Member);
    expect($pivot->accepted_at)->toBeNull();
    Mail::assertQueued(MagicLinkMail::class, fn ($mail) => $mail->hasTo($invitee->email));
});

it('reuses an existing user and does not duplicate membership when inviting the same email twice', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);

    $this->actingAs($owner)->post(route('groups.invite', $group), ['email' => 'invitee@example.com']);
    $this->actingAs($owner)->post(route('groups.invite', $group), ['email' => 'invitee@example.com']);

    expect(User::query()->where('email', 'invitee@example.com')->count())->toBe(1);
    $invitee = User::query()->where('email', 'invitee@example.com')->firstOrFail();
    expect($group->members()->whereKey($invitee->id)->count())->toBe(1);
    Mail::assertQueued(MagicLinkMail::class, 2);
});

it('forbids a member from inviting new members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner);
    $group->addMember($member, GroupRole::Member);

    $response = $this->actingAs($member)->post(route('groups.invite', $group), ['email' => 'invitee@example.com']);

    $response->assertForbidden();
});

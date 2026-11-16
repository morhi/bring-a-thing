<?php

use App\Enums\GroupRole;
use App\Models\Event;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

/**
 * Create a user who is a member of two groups, plus a third group they do not belong to.
 *
 * @return array{0: User, 1: Group, 2: Group, 3: Group}
 */
function memberOfTwoGroups(): array
{
    $user = User::factory()->create();
    $first = Group::factory()->create(['name' => 'Alpha']);
    $second = Group::factory()->create(['name' => 'Beta']);
    $first->addMember($user, GroupRole::Member, now());
    $second->addMember($user, GroupRole::Owner, now());

    return [$user, $first, $second, Group::factory()->create()];
}

/**
 * Valid store input and the model class for the entry's kind.
 *
 * @return array{0: array<string, mixed>, 1: class-string<Poll|Event>}
 */
function createWithoutGroupInput(string $kind): array
{
    if ($kind === 'poll') {
        return [[
            'title' => 'Where to?',
            'type' => 'date_finder',
            'granularity' => 'day',
            'options' => [['date' => '2027-10-01', 'starts_at' => null, 'ends_at' => null, 'label' => null]],
        ], Poll::class];
    }

    return [['title' => 'Where to?', 'date' => '2027-10-01'], Event::class];
}

// The rule is the same for polls (F-06) and events (F-10), so one set of tests is registered per kind.
foreach (['poll' => ['F-06', 'Polls/Create'], 'event' => ['F-10', 'Events/Create']] as $kind => [$feature, $component]) {
    it("{$feature}: redirects a guest from the create page outside a group to the start page", function () use ($kind) {
        $this->get(route("{$kind}s.create-any"))->assertRedirect(route('login'));
    });

    it("{$feature}: offers only the own groups on the create page outside a group", function () use ($kind, $component) {
        [$user, , , $foreign] = memberOfTwoGroups();

        $this->actingAs($user)->get(route("{$kind}s.create-any"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component($component)
                ->where('form.groups', fn ($groups) => collect($groups)->pluck('title')->all() === ['Alpha', 'Beta']
                    && ! collect($groups)->pluck('slug')->contains($foreign->slug)));
    });

    it("{$feature}: creates in the chosen group when posting from the create page outside a group", function () use ($kind) {
        [$user, , $second] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);

        $this->actingAs($user)->post(route("{$kind}s.store", $second), $input)->assertSessionHasNoErrors();

        expect($model::query()->where('title', 'Where to?')->firstOrFail()->group_id)->toBe($second->id);
    });

    it("{$feature}: rejects a group the user is not a member of when posting from the create page outside a group", function () use ($kind) {
        [$user, , , $foreign] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);

        $this->actingAs($user)->post(route("{$kind}s.store", $foreign), $input)->assertForbidden();

        expect($model::query()->count())->toBe(0);
    });
}

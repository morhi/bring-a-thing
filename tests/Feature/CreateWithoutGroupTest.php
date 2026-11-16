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

    it("{$feature}: creates in the group of the group-bound store route", function () use ($kind) {
        [$user, , $second] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);

        $this->actingAs($user)->post(route("{$kind}s.store", $second), $input)->assertSessionHasNoErrors();

        expect($model::query()->where('title', 'Where to?')->firstOrFail()->group_id)->toBe($second->id);
    });

    it("{$feature}: rejects a group-bound store route of a group the user is not a member of", function () use ($kind) {
        [$user, , , $foreign] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);

        $this->actingAs($user)->post(route("{$kind}s.store", $foreign), $input)->assertForbidden();

        expect($model::query()->count())->toBe(0);
    });

    it("{$feature}: creates a standalone {$kind} through the store route without a group", function () use ($kind) {
        [$user] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);

        $response = $this->actingAs($user)->post(route("{$kind}s.store-any"), $input)->assertSessionHasNoErrors();

        $item = $model::query()->where('title', 'Where to?')->firstOrFail();
        $response->assertRedirect(route("{$kind}s.show", $item))->assertSessionHas('success');
        expect($item->group_id)->toBeNull()
            ->and($item->organizer_id)->toBe($user->id);
        $this->actingAs($user)->get(route("{$kind}s.show", $item))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where("page.{$kind}.group_slug", null)->where("page.{$kind}.group_name", null));
    });

    it("{$feature}: the store route without a group creates in a group only with its membership", function (Closure $pickGroup, ?int $status) use ($kind) {
        [$user, $first, , $foreign] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);
        $group = $pickGroup($first, $foreign);

        $response = $this->actingAs($user)->post(route("{$kind}s.store-any"), [...$input, 'group_id' => $group?->id ?? 999999]);

        if ($status !== null) {
            $response->assertStatus($status);
            expect($model::query()->count())->toBe(0);

            return;
        }

        $response->assertSessionHasErrors('group_id');
        expect($model::query()->count())->toBe(0);
    })->with([
        'foreign group' => [fn ($first, $foreign) => $foreign, 403],
        'missing group' => [fn () => null, null],
    ]);

    it("{$feature}: the store route without a group creates in an own group", function () use ($kind) {
        [$user, $first] = memberOfTwoGroups();
        [$input, $model] = createWithoutGroupInput($kind);

        $this->actingAs($user)->post(route("{$kind}s.store-any"), [...$input, 'group_id' => $first->id])->assertSessionHasNoErrors();

        expect($model::query()->where('title', 'Where to?')->firstOrFail()->group_id)->toBe($first->id);
    });
}

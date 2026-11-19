<?php

use App\Enums\GroupRole;
use App\Enums\ParticipationStatus;
use App\Enums\PollResponseStatus;
use App\Models\Event;
use App\Models\EventParticipation;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Roster;
use App\Models\RosterItem;
use App\Models\RosterItemClaim;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Passkeys\Passkey;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create a group with an owner and the given number of plain members, and a date finder poll by the owner.
 *
 * @return array{0: Group, 1: User, 2: array<int, User>, 3: Poll}
 */
function pollGroup(int $memberCount = 2, array $pollAttributes = []): array
{
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());

    $members = User::factory()->count($memberCount)->create()->all();

    foreach ($members as $member) {
        $group->addMember($member, GroupRole::Member, now());
    }

    $poll = Poll::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id, ...$pollAttributes]);

    return [$group, $owner, $members, $poll];
}

/**
 * Record answers on an option, one per given user.
 *
 * @param  array<int, User>  $users
 */
function answerOption(PollOption $option, array $users, PollResponseStatus $status): void
{
    foreach ($users as $user) {
        $option->responses()->create(['user_id' => $user->id, 'status' => $status]);
    }
}

/**
 * Give the user a grant on the event or poll, as joining through its link does.
 */
function grantOn(Event|Poll $entity, User $user): void
{
    $entity->accessGrants()->create(['user_id' => $user->id, 'joined_via' => 'link']);
}

/**
 * Create a group with its owner and one plain member.
 *
 * @return array{0: Group, 1: User, 2: User}
 */
function dashboardGroup(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $group->addMember($owner, GroupRole::Owner, now());
    $group->addMember($member, GroupRole::Member, now());

    return [$group, $owner, $member];
}

/**
 * Create a poll with the given number of options, answered "yes" on the first few by the user; standalone without a group.
 */
function dashboardPoll(?Group $group, User $organizer, int $options, ?User $user = null, int $answered = 0, array $attributes = []): Poll
{
    $poll = Poll::factory()->create(['group_id' => $group?->id, 'organizer_id' => $organizer->id, ...$attributes]);
    $created = PollOption::factory()->count($options)->create(['poll_id' => $poll->id]);

    foreach ($created->take($answered) as $option) {
        $option->responses()->create(['user_id' => $user->id, 'status' => PollResponseStatus::Yes]);
    }

    return $poll;
}

/**
 * Create an event in the group, or standalone without one, with the user's participation status.
 */
function dashboardEvent(?Group $group, User $organizer, ?User $user = null, ?ParticipationStatus $status = null, array $attributes = []): Event
{
    $event = Event::factory()->create(['group_id' => $group?->id, 'organizer_id' => $organizer->id, ...$attributes]);

    if ($user !== null && $status !== null) {
        EventParticipation::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => $status]);
    }

    return $event;
}

/**
 * Create a list with two items, the first one claimed by the given user.
 */
function dashboardList(User $owner, array $attributes = [], ?User $claimer = null): Roster
{
    $roster = Roster::factory()->create(['owner_id' => $owner->id, ...$attributes]);
    $first = RosterItem::factory()->create(['roster_id' => $roster->id]);
    RosterItem::factory()->create(['roster_id' => $roster->id]);

    if ($claimer !== null) {
        RosterItemClaim::factory()->create(['roster_item_id' => $first->id, 'user_id' => $claimer->id]);
    }

    return $roster;
}

/**
 * Store a passkey row for the user without running a WebAuthn ceremony.
 */
function createPasskeyFor(User $user, string $name = 'MacBook'): Passkey
{
    return $user->passkeys()->forceCreate([
        'name' => $name,
        'credential_id' => fake()->unique()->sha1(),
        'credential' => ['type' => 'public-key'],
    ]);
}

/**
 * Read the remember cookie from a response, in the form a browser would send it back.
 */
function recallerFrom(TestResponse $response): string
{
    $cookie = $response->getCookie(Auth::guard()->getRecallerName(), decrypt: false);

    expect($cookie)->not->toBeNull();

    return $cookie->getValue();
}

/**
 * Forget the session and the resolved guard user, so only a remember cookie can log the next request in.
 */
function dropSession(): void
{
    app('session.store')->flush();
    Auth::forgetGuards();
}

/**
 * Enforce the CSRF token check, which the framework skips while running unit tests.
 */
function realCsrf(): void
{
    app()->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });
}

/**
 * Assert that a request carrying only the remember cookie reaches a protected route as the user.
 */
function assertRestoredFrom(string $recaller, User $user, string $url): void
{
    test()->withUnencryptedCookie(Auth::guard()->getRecallerName(), $recaller)
        ->get($url)
        ->assertOk();

    test()->assertAuthenticatedAs($user);
    expect(Auth::viaRemember())->toBeTrue();
}

/**
 * Log the user in through a signed login link and return the remember cookie it set.
 */
function loginViaLink(User $user): string
{
    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id]);

    return recallerFrom(test()->get($url));
}

/**
 * Build a remember cookie that still finds the user but no longer matches the password hash.
 *
 * The guard stays a guest yet reports viaRemember(), as a cookie left over from an old login does.
 */
function staleRecallerFor(User $user): string
{
    $recaller = loginViaLink($user);
    dropSession();
    $user->update(['password' => 'changed-pw']);

    return $recaller;
}

/**
 * Assert the session passes the recent-login gate of the passkey routes.
 */
function assertWindowOpen(): void
{
    test()->getJson(route('passkey.registration-options'))->assertOk();
}

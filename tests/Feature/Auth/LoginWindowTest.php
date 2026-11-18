<?php

use App\Http\Middleware\EnsureRecentLogin;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * Build the remember cookie a case sends, or null for none.
 */
function windowCookie(string $case, ?User $same = null): ?string
{
    return match ($case) {
        'none' => null,
        'other account' => tap(loginViaLink(User::factory()->create()), fn () => dropSession()),
        'same account' => tap(loginViaLink($same), fn () => dropSession()),
        'stale cookie' => staleRecallerFor(User::factory()->create()),
    };
}

function withWindowCookie(?string $recaller): mixed
{
    return $recaller === null
        ? test()
        : test()->withUnencryptedCookie(Auth::guard()->getRecallerName(), $recaller);
}

it('F-02: W-1 a login link starts the window with no, another, the same or a stale remember cookie', function (string $case) {
    $user = User::factory()->create();
    $recaller = windowCookie($case, $user);
    $url = URL::temporarySignedRoute('login.consume', now()->addMinutes(30), ['user' => $user->id]);

    withWindowCookie($recaller)->get($url);

    $this->assertAuthenticatedAs($user);
    assertWindowOpen();
})->with(['none', 'other account', 'same account', 'stale cookie']);

it('F-02: W-2 a password login starts the window with no or a stale remember cookie', function (string $case) {
    $user = User::factory()->create(['password' => 'correct-password']);
    $recaller = windowCookie($case);

    withWindowCookie($recaller)
        ->post(route('login.password'), ['email' => $user->email, 'password' => 'correct-password'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    assertWindowOpen();
})->with(['none', 'stale cookie']);

it('F-02: W-3 a passkey login starts the window with no or a stale remember cookie', function (string $case) {
    $user = User::factory()->create();
    $passkey = createPasskeyFor($user);
    $recaller = windowCookie($case);

    // Only the signature check is faked; route, guard, login, session and response class are real.
    app()->bind(VerifyPasskey::class, fn () => new class($passkey) extends VerifyPasskey
    {
        public function __construct(private readonly Passkey $passkey) {}

        public function __invoke(PublicKeyCredential $credential, PublicKeyCredentialRequestOptions $options, ?PasskeyUser $user = null): Passkey
        {
            return $this->passkey;
        }
    });

    $this->getJson(route('passkey.login-options'))->assertOk();
    $field = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    withWindowCookie($recaller)
        ->post(route('passkey.login'), ['credential' => [
            'id' => $field('credential-id'),
            'rawId' => $field('credential-id'),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $field('{"type":"webauthn.get","challenge":"Y2hhbGxlbmdl","origin":"http://localhost"}'),
                'authenticatorData' => $field(str_repeat("\0", 32)."\x01".str_repeat("\0", 4)),
                'signature' => $field('signature'),
                'userHandle' => $field((string) $user->id),
            ],
        ]], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('redirect', route('dashboard'));

    $this->assertAuthenticatedAs($user);
    assertWindowOpen();
})->with(['none', 'stale cookie']);

it('F-26: W-4 a name-only join starts the window with no or a stale remember cookie', function (string $case) {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $event = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id]);
    $token = $event->enableInviteLink();
    $recaller = windowCookie($case);

    withWindowCookie($recaller)->post(route('events.join.store', $token), ['name' => 'Name Only']);

    $guest = User::query()->where('name', 'Name Only')->firstOrFail();
    $this->assertAuthenticatedAs($guest);
    // With a password the gate applies, which makes the window observable.
    $guest->update(['password' => 'new-password']);
    Auth::forgetGuards();
    assertWindowOpen();
})->with(['none', 'stale cookie']);

it('F-02: G-1 a name-only guest can save its first passkey on a remember-restored session', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $token = Event::factory()->create(['group_id' => $group->id, 'organizer_id' => $owner->id])->enableInviteLink();
    $recaller = recallerFrom($this->post(route('events.join.store', $token), ['name' => 'Name Only']));
    dropSession();

    $this->withUnencryptedCookie(Auth::guard()->getRecallerName(), $recaller)
        ->get(route('passkey.registration-options'), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['options' => ['challenge']]);

    expect(Auth::viaRemember())->toBeTrue();
});

it('F-02: G-2 a restored session with an email, a password or a passkey gets 403 with the next step and no window', function (string $kind) {
    // The shadow factory keeps its email, so 'email only' has neither password nor passkey.
    $user = $kind === 'password only' ? User::factory()->create() : User::factory()->shadow()->create();

    if ($kind === 'passkey only') {
        createPasskeyFor($user);
    }

    $recaller = loginViaLink($user);
    dropSession();

    $this->withUnencryptedCookie(Auth::guard()->getRecallerName(), $recaller)
        ->get(route('passkey.registration-options'), ['Accept' => 'application/json'])
        ->assertForbidden()
        ->assertJsonPath('message', __('ui.settings.passkeys.recentLogin', ['minutes' => 15]));

    expect(session()->has(EnsureRecentLogin::SESSION_KEY))->toBeFalse();
})->with(['password only', 'passkey only', 'email only']);

it('F-02: G-3 the window lasts exactly 15 minutes', function (int $seconds, int $status) {
    $user = User::factory()->create();
    $this->freezeTime();

    $this->actingAs($user)
        ->withSession([EnsureRecentLogin::SESSION_KEY => now()->subSeconds($seconds)->timestamp])
        ->getJson(route('passkey.registration-options'))
        ->assertStatus($status);
})->with([
    'exactly 15:00' => [900, 200],
    '15:01' => [901, 403],
]);

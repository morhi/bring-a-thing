<?php

use App\Mail\MagicLinkMail;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;

afterEach(function () {
    URL::forceHttps(false);
    URL::forceScheme(null);
    URL::forceRootUrl(null);
});

it('F-01: behind a TLS proxy the login link uses https and validates when opened', function () {
    // Explicit http URLs below: the default test request follows APP_URL, which is https under ddev and would hide the proxy.
    Mail::fake();
    $user = User::factory()->create();
    $proxy = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 203.0.113.5'];

    $this->withServerVariables($proxy)->post('http://localhost/login', ['email' => $user->email]);

    // Only the proxy's own entry counts: a client-supplied entry left of it must not become the client IP.
    expect(request()->ip())->toBe('203.0.113.5');

    $url = null;
    Mail::assertQueued(MagicLinkMail::class, function (MagicLinkMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    expect($url)->toStartWith('https://');

    $this->withServerVariables($proxy)->get(str_replace('https://', 'http://', $url))->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $this->flushSession();

    $this->withServerVariables([]);
    $this->get(str_replace('https://', 'http://', $url))->assertRedirect(route('welcome'))->assertSessionHas('login_link_error', 'invalid');
    $this->assertGuest();
});

it('F-01: an Inertia request with an expired CSRF token redirects back with an error flash', function () {
    realCsrf();

    $response = $this->from(route('welcome'))
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'test'])
        ->post(route('login.password'), ['email' => 'a@example.com', 'password' => 'secret']);

    $response->assertRedirect(route('welcome'))->assertSessionHas('error', __('ui.error.pageExpired'));
    $this->assertGuest();
});

it('F-01: a JSON request with an expired CSRF token keeps 419', function () {
    realCsrf();

    $response = $this->postJson(route('login.password'), ['email' => 'a@example.com', 'password' => 'secret']);

    $response->assertStatus(419)->assertJson(['message' => 'CSRF token mismatch.']);
    $response->assertSessionMissing('error');
});

it('F-01: in production URLs are forced to https, elsewhere they are not', function (string $env, string $scheme) {
    config(['app.url' => 'https://bringathing.com']);
    app()->detectEnvironment(fn () => $env);
    URL::forceScheme('http');

    (new class(app()) extends AppServiceProvider
    {
        public function run(): void
        {
            $this->configureHttps();
        }
    })->run();

    $signed = URL::temporarySignedRoute('login.consume', now()->addMinute(), ['user' => 1]);

    expect(route('welcome'))->toStartWith($scheme.'://')
        ->and($signed)->toStartWith($scheme.'://');

    if ($env === 'production') {
        expect(route('welcome'))->toBe('https://bringathing.com')
            ->and($signed)->toStartWith('https://bringathing.com/');
    }
})->with([
    'production' => ['production', 'https'],
    'testing' => ['testing', 'http'],
    'local' => ['local', 'http'],
]);

it('F-01: in production the generated frontend routes stay relative to the current host', function () {
    $path = storage_path('framework/testing/wayfinder-'.uniqid());

    // Artisan dispatches the command events only through its real entry point, so a child process runs it.
    $process = new Process([PHP_BINARY, 'artisan', 'wayfinder:generate', '--path='.$path], base_path(), [
        'APP_ENV' => 'production',
        'APP_URL' => 'https://bringathing.com',
    ]);

    try {
        $process->mustRun();

        $generated = collect(File::allFiles($path))->map(fn ($file) => $file->getContents())->implode("\n");
    } finally {
        File::deleteDirectory($path);
    }

    expect($generated)->toContain("url: '/login'")
        ->and($generated)->not->toContain('bringathing.com');
});

it('F-01: the session cookie secure default depends on the environment', function (string $env, ?string $override, bool $expected) {
    // A child process keeps the env override out of this process, where the Env repository is immutable.
    $code = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo json_encode((bool) config("session.secure"));';
    $process = new Process([PHP_BINARY, '-r', $code], base_path(), [
        'APP_ENV' => $env,
        'SESSION_SECURE_COOKIE' => $override ?? false,
    ]);
    $process->mustRun();

    expect(json_decode($process->getOutput()))->toBe($expected);
})->with([
    'production default' => ['production', null, true],
    'testing default' => ['testing', null, false],
    'local default' => ['local', null, false],
    'production override' => ['production', 'false', false],
]);

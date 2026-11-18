<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MatchPasskeyHost;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Env;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // REMOTE_ADDR trusts only the one proxy that connects to the app. '*' and '**' trust every hop, so clients
        // could spoof their IP via X-Forwarded-For and bypass throttling: set them only as an explicit choice.
        // Read through Env because config is not available this early.
        $trustedProxies = (string) Env::get('TRUSTED_PROXIES', 'REMOTE_ADDR');
        $middleware->trustProxies(at: in_array($trustedProxies, ['*', '**'], true)
            ? $trustedProxies
            : array_map('trim', explode(',', $trustedProxies)));

        $middleware->web(append: [
            SetLocale::class,
            MatchPasskeyHost::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired token on an Inertia visit would otherwise show the raw 419 page.
        // The handler has already turned the TokenMismatchException into a 419 HttpException by now.
        $exceptions->render(function (HttpException $e, Request $request): ?RedirectResponse {
            if (! $e->getPrevious() instanceof TokenMismatchException || ! $request->header('X-Inertia')) {
                return null;
            }

            return back()->with('error', __('ui.error.pageExpired'));
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

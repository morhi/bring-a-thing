<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentLogin
{
    public const SESSION_KEY = 'auth.logged_in_at';

    private const MAX_AGE_MINUTES = 15;

    /**
     * Block passkey changes unless the session logged in recently (F-02).
     *
     * A stolen or unattended session must not be able to plant a passkey
     * that outlives it. A guest with no email, password or passkey is let
     * through: it could not log in again after being told to log out.
     * Accounts with an email keep the window, a login link reopens it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->email === null && $user->password === null && ! $user->passkeys()->exists()) {
            return $next($request);
        }

        $loggedInAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($loggedInAt >= now()->subMinutes(self::MAX_AGE_MINUTES)->timestamp) {
            return $next($request);
        }

        $message = __('ui.settings.passkeys.recentLogin', ['minutes' => self::MAX_AGE_MINUTES]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}

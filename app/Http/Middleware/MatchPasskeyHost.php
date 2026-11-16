<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MatchPasskeyHost
{
    /**
     * Bind the passkey relying party to the host the browser actually uses.
     *
     * A host outside the APP_URL domain (e.g. www vs apex) makes iOS Safari reject
     * the ceremony with a SecurityError, because the RP ID is not a suffix of it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $requestHost = $request->getHost();

        if (! is_string($configuredHost) || $requestHost === $configuredHost || str_ends_with($requestHost, '.'.$configuredHost)) {
            return $next($request);
        }

        config([
            'passkeys.relying_party_id' => $requestHost,
            'passkeys.allowed_origins' => [...config('passkeys.allowed_origins'), $request->getSchemeAndHttpHost()],
        ]);

        return $next($request);
    }
}

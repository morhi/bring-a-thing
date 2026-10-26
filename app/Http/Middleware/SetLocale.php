<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the interface language for this request.
     *
     * Order: the account's saved language, the guest's choice kept in the
     * session by the language switch, the browser's Accept-Language header,
     * then English.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request)->value);

        return $next($request);
    }

    /**
     * Resolve the locale for the request following the documented order.
     */
    private function resolve(Request $request): Locale
    {
        return Locale::fromSupported($request->user()?->language)
            ?? Locale::fromSupported($request->hasSession() ? $request->session()->get('locale') : null)
            ?? Locale::fromBrowser($request);
    }
}

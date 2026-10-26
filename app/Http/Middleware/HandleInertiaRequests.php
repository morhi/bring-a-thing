<?php

namespace App\Http\Middleware;

use App\Actions\I18n\LoadInterfaceTranslations;
use App\Data\SharedPropsData;
use App\Enums\Locale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = Locale::fromSupported(app()->getLocale()) ?? Locale::fallback();

        return [
            ...parent::share($request),
            ...SharedPropsData::fromRequest($request)->toArray(),
            // Once prop keyed per locale: sent a single time per language, and a language switch
            // changes the key so the client fetches the new strings instead of keeping the old.
            'translations' => Inertia::once(fn () => app(LoadInterfaceTranslations::class)->handle($locale))
                ->as("translations.{$locale->value}"),
        ];
    }
}

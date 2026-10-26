<?php

namespace App\Actions\I18n;

use App\Enums\Locale;
use Illuminate\Support\Facades\Lang;

class LoadInterfaceTranslations
{
    /**
     * Load the whole `ui` string group for a locale (F-24).
     *
     * English is the base, so a key missing in another language still shows text.
     *
     * @return array<string, string>
     */
    public function handle(Locale $locale): array
    {
        $loader = Lang::getLoader();
        $fallback = $loader->load(Locale::fallback()->value, 'ui');

        if ($locale === Locale::fallback()) {
            return $fallback;
        }

        return [...$fallback, ...$loader->load($locale->value, 'ui')];
    }
}

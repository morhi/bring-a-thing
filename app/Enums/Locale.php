<?php

namespace App\Enums;

use Illuminate\Http\Request;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum Locale: string
{
    case En = 'en';
    case De = 'de';

    /**
     * The locale used when nothing else can be resolved.
     */
    public static function fallback(): self
    {
        return self::En;
    }

    /**
     * Parse a raw value into a supported locale, or null when unsupported.
     */
    public static function fromSupported(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom($value) : null;
    }

    /**
     * Pick the best supported locale from the request's Accept-Language header.
     *
     * English is listed first, so a browser asking for neither language gets English.
     */
    public static function fromBrowser(Request $request): self
    {
        $preferred = $request->getPreferredLanguage(array_column(self::cases(), 'value'));

        return self::fromSupported($preferred) ?? self::fallback();
    }

    /**
     * The supported locale codes, for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

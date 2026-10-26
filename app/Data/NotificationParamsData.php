<?php

namespace App\Data;

use Illuminate\Support\Facades\Date;
use Spatie\LaravelData\Data;

class NotificationParamsData extends Data
{
    /**
     * The placeholders of a notification text (F-23).
     *
     * Names and titles are content people wrote and stay untranslated
     * (F-24); the date is an ISO date, so the viewer's language formats it.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $title = null,
        public ?string $group = null,
        public ?string $list = null,
        public ?string $item = null,
        public ?string $date = null,
    ) {}

    /**
     * Read the params stored with a notification, ignoring unknown keys.
     *
     * @param  array<string, mixed>  $params
     */
    public static function fromStored(array $params): self
    {
        return new self(
            name: self::stringOrNull($params['name'] ?? null),
            title: self::stringOrNull($params['title'] ?? null),
            group: self::stringOrNull($params['group'] ?? null),
            list: self::stringOrNull($params['list'] ?? null),
            item: self::stringOrNull($params['item'] ?? null),
            date: self::stringOrNull($params['date'] ?? null),
        );
    }

    /**
     * Fill the placeholders for the server-rendered text, with the date formatted for the locale (section 9).
     *
     * @return array<string, string>
     */
    public function replacements(string $locale): array
    {
        $replacements = array_map(fn (?string $value) => $value ?? '', $this->toArray());

        if ($this->date !== null) {
            $replacements['date'] = $this->formatDate($this->date, $locale);
        }

        return $replacements;
    }

    /**
     * Format an ISO date as "15.10.2026" in German and "15 Oct 2026" in English.
     */
    private function formatDate(string $date, string $locale): string
    {
        $carbon = rescue(fn () => Date::parse($date), report: false);

        if ($carbon === null) {
            return $date;
        }

        return $carbon->locale($locale)->translatedFormat($locale === 'de' ? 'd.m.Y' : 'j M Y');
    }

    /**
     * Cast a stored scalar to a string, keeping null.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}

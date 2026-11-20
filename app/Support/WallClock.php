<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WallClock
{
    /**
     * The zone for people without a usable setting and for output not yet tied to a person (section 9).
     */
    public const string DEFAULT_ZONE = 'Europe/Zurich';

    /**
     * Resolve the zone a person's entered and shown times count in (F-02, section 9).
     */
    public static function zoneOf(?User $user): string
    {
        return $user?->timezone ?? self::DEFAULT_ZONE;
    }

    /**
     * Turn a local date and optional time into the stored pair: a plain date without a time, a UTC instant with one (section 9).
     *
     * A date without a time carries no zone, so it stays as entered.
     *
     * @return array{0: string|null, 1: Carbon|null}
     */
    public static function toStored(string $date, ?string $time, string $zone): array
    {
        if ($time === null || $time === '') {
            return [substr($date, 0, 10), null];
        }

        return [null, self::instant($date, $time, $zone)];
    }

    /**
     * The UTC instant of a wall-clock date and time in the given zone.
     */
    public static function instant(string $date, string $time, string $zone): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', substr($date, 0, 10).' '.substr($time, 0, 5), $zone)->utc();
    }

    /**
     * Format an instant as ISO 8601 in UTC, the shape the frontend receives (section 9).
     */
    public static function iso(?CarbonInterface $instant): ?string
    {
        return $instant?->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}

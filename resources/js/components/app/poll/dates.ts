import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Locale } from '@/lib/i18n';

const page = usePage();

function browserZone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * Resolve the zone every time is shown and entered in (section 9 Time zones).
 *
 * A logged-in account uses its own zone, because the server converts submitted
 * times with it; the browser zone would shift edits made on another device.
 * Guests and accounts without a zone yet fall back to the browser zone.
 */
export const viewerZone = computed<string>(() => {
    const zone = page.props.auth?.user?.timezone;

    return typeof zone === 'string' && zone !== '' ? zone : browserZone();
});

const partFormatters = new Map<string, Intl.DateTimeFormat>();

function partsIn(instant: string, zone: string): Record<string, string> {
    let formatter = partFormatters.get(zone);

    if (!formatter) {
        formatter = new Intl.DateTimeFormat('en-US', {
            timeZone: zone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        });
        partFormatters.set(zone, formatter);
    }

    return Object.fromEntries(
        formatter
            .formatToParts(new Date(instant))
            .map((part) => [part.type, part.value]),
    );
}

/**
 * Derive the YYYY-MM-DD day of a UTC instant in the viewer's zone.
 */
export function localDay(instant: string, zone = viewerZone.value): string {
    const parts = partsIn(instant, zone);

    return `${parts.year}-${parts.month}-${parts.day}`;
}

/**
 * Derive the HH:mm wall-clock time of a UTC instant in the viewer's zone.
 */
export function localTime(instant: string, zone = viewerZone.value): string {
    const parts = partsIn(instant, zone);

    return `${parts.hour}:${parts.minute}`;
}

/**
 * Pick the shown day of a dated value: the local start day when it has a time, else its plain date.
 *
 * Returns an empty string for an undated value, so callers can guard with a falsy check.
 */
export function dayOf(value: {
    date: string | null;
    starts_at: string | null;
}): string {
    return value.starts_at ? localDay(value.starts_at) : (value.date ?? '');
}

/**
 * Format an optional instant as local HH:mm, or null when there is no time.
 */
export function timeOf(instant: string | null): string | null {
    return instant ? localTime(instant) : null;
}

/**
 * Map the interface language to the date locale: "15 Oct 2026" in English, "15.10.2026" in German.
 */
export function dateLocale(language: Locale): string {
    return language === 'de' ? 'de-CH' : 'en-GB';
}

/**
 * Parse a YYYY-MM-DD calendar date as a local date, so no time zone shifts the day.
 */
export function fromIso(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

export function toIso(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export function addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
}

export function mondayOf(date: Date): Date {
    const offset = (date.getDay() + 6) % 7;

    return addDays(
        new Date(date.getFullYear(), date.getMonth(), date.getDate()),
        -offset,
    );
}

export function toMinutes(time: string): number {
    const [hours, minutes] = time.split(':').map(Number);

    return hours * 60 + minutes;
}

export function toTime(minutes: number): string {
    return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

export function formatDate(
    value: string,
    language: Locale,
    options: Intl.DateTimeFormatOptions,
): string {
    // German short weekdays carry a period ("Sa."), which the compact headers drop
    return fromIso(value)
        .toLocaleDateString(dateLocale(language), options)
        .replace('.,', ',');
}

/**
 * Format a UTC instant as its local day in the viewer's zone, optionally followed by the local time.
 */
export function formatInstant(
    instant: string,
    language: Locale,
    options: Intl.DateTimeFormatOptions,
    withTime = false,
): string {
    const day = formatDate(localDay(instant), language, options);

    return withTime ? `${day}, ${localTime(instant)}` : day;
}

/**
 * Describe an option in words for labels and screen readers: weekday, date, time and label.
 */
export function describeOption(
    option: {
        date: string;
        start: string | null;
        end: string | null;
        label: string | null;
    },
    language: Locale,
): string {
    const parts = [
        formatDate(option.date, language, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
    ];

    if (option.start && option.end) {
        parts.push(`${option.start}–${option.end}`);
    }

    if (option.label) {
        parts.push(option.label);
    }

    return parts.join(', ');
}

import type { Locale } from '@/lib/i18n';

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

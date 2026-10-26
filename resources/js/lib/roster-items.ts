import { dateLocale, formatDate } from '@/components/app/poll/dates';
import type { Locale } from '@/lib/i18n';
import { t } from '@/lib/i18n';

export type ItemDateGroup = {
    key: string;
    label: string;
    items: App.Data.RosterItemData[];
};

/**
 * Group the items by their effective date (F-14).
 *
 * Only splits when at least one item carries its own date; undated items go last.
 */
export function groupItemsByDate(
    items: App.Data.RosterItemData[],
    language: Locale,
): ItemDateGroup[] {
    if (!items.some((item) => item.date !== null)) {
        return [{ key: 'all', label: '', items }];
    }

    const byDate = new Map<string, App.Data.RosterItemData[]>();

    for (const item of items) {
        const key = item.effective_date ?? '';

        byDate.set(key, [...(byDate.get(key) ?? []), item]);
    }

    return [...byDate.entries()]
        .sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : a.localeCompare(b)))
        .map(([key, entries]) => ({
            key: key || 'none',
            label: key
                ? formatDate(key, language, {
                      weekday: 'long',
                      day: 'numeric',
                      month: 'long',
                      year: 'numeric',
                  })
                : t('list.items.noDate'),
            items: entries,
        }));
}

export function formatAmount(
    value: number,
    unit: string | null,
    language: Locale,
): string {
    const number = value.toLocaleString(dateLocale(language));

    return unit ? `${number} ${unit}` : number;
}

/**
 * Name the distance to a future date: today, tomorrow or in n days.
 */
export function relativeDay(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const today = new Date();
    const days = Math.round(
        (Date.UTC(year, month - 1, day) -
            Date.UTC(today.getFullYear(), today.getMonth(), today.getDate())) /
            86_400_000,
    );

    if (days <= 0) {
        return t('common.today');
    }

    if (days === 1) {
        return t('common.tomorrow');
    }

    return t('common.inDays', { n: days });
}

export function itemFields(
    item: App.Data.RosterItemData,
    fields: App.Data.CustomFieldData[],
): { name: string; value: string }[] {
    return fields.flatMap((field) => {
        const value = item.custom_field_values.find(
            (entry) => entry.custom_field_id === field.id,
        )?.value;

        return value ? [{ name: field.name, value }] : [];
    });
}

/**
 * Label one attendance day: weekday and date, then time and label of a slot (F-18).
 */
export function attendanceDayLabel(
    day: { date: string; starts_at: string | null; label: string | null },
    language: Locale,
): string {
    const date = formatDate(day.date, language, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
    const parts = [date, day.starts_at, day.label].filter(
        (part): part is string => !!part,
    );

    return parts.join(', ');
}

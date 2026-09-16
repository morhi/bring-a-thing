import type { PollOption } from '@/types';

export function formatDateOnly(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function formatTimeOnly(date: Date): string {
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

/** Human-readable label for a poll option: date, plus time range and label when the poll uses hour slots. */
export function formatPollOption(option: PollOption): string {
    const dateLabel = new Date(option.date).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });

    if (!option.starts_at) {
        return dateLabel;
    }

    const timeLabel = option.ends_at
        ? `${option.starts_at}–${option.ends_at}`
        : option.starts_at;

    return option.label
        ? `${dateLabel} · ${option.label} (${timeLabel})`
        : `${dateLabel} · ${timeLabel}`;
}

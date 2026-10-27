import { toMinutes } from '@/components/app/poll/dates';

// Visible window of the week grids; a drawing surface, not a domain limit
export const HOUR_START = 7;
export const HOUR_END = 23;
export const HOUR_HEIGHT = 44;
export const HOURS_COLUMN = 52;

/**
 * Format an hour for the week grid axis, e.g. "07:00".
 */
export function hourLabel(hour: number): string {
    return `${String(hour).padStart(2, '0')}:00`;
}

/**
 * Position a slot on the week grid by its start and end time.
 */
export function slotStyle(
    start: string,
    end: string,
    hourStart = HOUR_START,
    minHeight = 18,
): Record<string, string> {
    const top = ((toMinutes(start) - hourStart * 60) / 60) * HOUR_HEIGHT;
    const height = ((toMinutes(end) - toMinutes(start)) / 60) * HOUR_HEIGHT;

    return { top: `${top}px`, height: `${Math.max(height, minHeight)}px` };
}

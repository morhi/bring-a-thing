export type PollAnswer = 'yes' | 'maybe' | 'no';

export type PollKey = number | string;

export type OptionCalendarMode = 'day' | 'slots';

/**
 * One poll option as the grid shows it. Dates are YYYY-MM-DD, times HH:MM.
 */
export type PollGridOption = {
    id: PollKey;
    date: string;
    start: string | null;
    end: string | null;
    label: string | null;
    fixed?: boolean;
};

export type PollGridParticipant = {
    id: PollKey;
    name: string | null;
    email?: string | null;
};

/**
 * Answers keyed by participant id, then option id. A missing entry means no answer.
 */
export type PollAnswers = Record<string, Record<string, PollAnswer>>;

export type PollOptionTotals = {
    yes: number;
    maybe: number;
    no: number;
};

/**
 * An option while organizers edit a poll. Locked options have answers and keep date and time (F-08);
 * fixed options have an event and cannot be removed (F-09).
 */
export type OptionDraft = {
    id?: PollKey | null;
    date: string;
    start: string | null;
    end: string | null;
    label: string;
    locked?: boolean;
    fixed?: boolean;
    answerCount?: number;
};

export type SlotTime = {
    start: string;
    end: string;
};

import type { User } from '@/types/auth';

export type PollType = 'date_finder' | 'attendance';
export type PollGranularity = 'day' | 'hour';
export type PollResponseStatus = 'yes' | 'no' | 'maybe';

export type PollResponse = {
    id: number;
    poll_option_id: number;
    user_id: number;
    status: PollResponseStatus;
    user?: User;
    [key: string]: unknown;
};

export type PollOption = {
    id: number;
    slug: string;
    poll_id: number;
    date: string;
    starts_at: string | null;
    ends_at: string | null;
    label: string | null;
    responses?: PollResponse[];
    poll?: Poll;
    [key: string]: unknown;
};

export type Poll = {
    id: number;
    slug: string;
    title: string;
    type: PollType;
    granularity: PollGranularity;
    organizer_id: number;
    group_id: number;
    closed_at: string | null;
    chosen_option_id: number | null;
    organizer?: User;
    options?: PollOption[];
    chosen_option?: PollOption | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

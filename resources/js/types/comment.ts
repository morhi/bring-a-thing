import type { User } from '@/types/auth';

export type Comment = {
    id: number;
    commentable_type: 'roster' | 'roster_item';
    commentable_id: number;
    user_id: number;
    body: string;
    user?: User;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

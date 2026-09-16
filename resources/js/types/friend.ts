import type { User } from '@/types/auth';

export type Friend = {
    id: number;
    slug: string;
    user_id: number;
    friend_user_id: number;
    name: string | null;
    friend_user?: User;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

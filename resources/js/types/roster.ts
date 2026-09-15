import type { Group } from '@/types/group';
import type { User } from '@/types/auth';

export type Roster = {
    id: number;
    title: string;
    description: string | null;
    date: string | null;
    owner_id: number;
    group_id: number | null;
    group?: Group | null;
    owner?: User;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

import type { User } from '@/types/auth';
import type { Roster } from '@/types/roster';

export type GroupRole = 'owner' | 'admin' | 'member';

export type GroupMember = User & {
    pivot: {
        role: GroupRole;
        accepted_at: string | null;
    };
};

export type Group = {
    id: number;
    slug: string;
    name: string;
    owner_id: number;
    created_at: string;
    updated_at: string;
    members?: GroupMember[];
    rosters?: Roster[];
    [key: string]: unknown;
};

import type { User } from '@/types/auth';

export type GroupRole = 'owner' | 'member';

export type GroupMember = User & {
    pivot: {
        role: GroupRole;
    };
};

export type Group = {
    id: number;
    name: string;
    owner_id: number;
    created_at: string;
    updated_at: string;
    members?: GroupMember[];
    [key: string]: unknown;
};

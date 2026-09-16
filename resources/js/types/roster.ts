import type { Group } from '@/types/group';
import type { User } from '@/types/auth';
import type { CustomField } from '@/types/customField';
import type { RosterItem } from '@/types/rosterItem';

export type Roster = {
    id: number;
    title: string;
    description: string | null;
    date: string | null;
    owner_id: number;
    group_id: number | null;
    members_can_add_items: boolean;
    share_token: string | null;
    group?: Group | null;
    owner?: User;
    items?: RosterItem[];
    custom_fields?: CustomField[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

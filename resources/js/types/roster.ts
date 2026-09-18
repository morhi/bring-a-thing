import type { Group } from '@/types/group';
import type { User } from '@/types/auth';
import type { CustomField } from '@/types/customField';
import type { RosterItem } from '@/types/rosterItem';
import type { Comment } from '@/types/comment';
import type { PollOption } from '@/types/poll';

export type Roster = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    date: string | null;
    owner_id: number;
    group_id: number | null;
    members_can_add_items: boolean;
    share_token: string | null;
    attendance_poll_option_id: number | null;
    group?: Group | null;
    owner?: User;
    items?: RosterItem[];
    custom_fields?: CustomField[];
    attendance_poll_option?: PollOption | null;
    comments?: Comment[];
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

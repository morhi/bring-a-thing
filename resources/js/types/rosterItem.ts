import type { User } from '@/types/auth';
import type { RosterItemCustomFieldValue } from '@/types/customField';

export type RosterItemClaim = {
    id: number;
    roster_item_id: number;
    user_id: number;
    quantity: string | null;
    user?: User;
    [key: string]: unknown;
};

export type RosterItem = {
    id: number;
    slug: string;
    roster_id: number;
    name: string;
    quantity: string | null;
    unit: string | null;
    notes: string | null;
    date: string | null;
    is_past: boolean | null;
    created_at: string;
    updated_at: string;
    claims?: RosterItemClaim[];
    custom_field_values?: RosterItemCustomFieldValue[];
    [key: string]: unknown;
};

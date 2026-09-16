export type SharedRosterClaim = {
    id: number;
    quantity: string | null;
    user_name: string;
};

export type SharedRosterItem = {
    id: number;
    slug: string;
    name: string;
    quantity: string | null;
    unit: string | null;
    notes: string | null;
    date: string | null;
    is_past: boolean | null;
    claims: SharedRosterClaim[];
    custom_field_values: {
        id: number;
        value: string | null;
        custom_field_id: number;
    }[];
};

export type SharedRoster = {
    title: string;
    description: string | null;
    date: string | null;
    can_add_items: boolean;
    custom_fields: { id: number; name: string }[];
    items: SharedRosterItem[];
};

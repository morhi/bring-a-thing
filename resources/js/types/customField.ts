export type CustomField = {
    id: number;
    slug: string;
    roster_id: number;
    name: string;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type RosterItemCustomFieldValue = {
    id: number;
    roster_item_id: number;
    custom_field_id: number;
    value: string | null;
    custom_field?: CustomField;
    [key: string]: unknown;
};

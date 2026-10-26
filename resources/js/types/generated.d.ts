declare namespace App {
namespace Data {
export type EventClaimData = {
item_name: string,
quantity: number | null,
unit: string | null,
roster_slug: string,
roster_title: string,
};
export type EventData = {
slug: string,
title: string,
description: string | null,
location: string | null,
date: string,
starts_at: string | null,
end_date: string | null,
ends_at: string | null,
is_past: boolean,
group_slug: string,
group_name: string,
organizer_id: number,
organizer_name: string | null,
origin_poll_slug: string | null,
parent_event: App.Data.EventSummaryData | null,
};
export type EventFormData = {
group_slug: string,
group_name: string,
member_count: number,
event: App.Data.EventData | null,
};
export type EventLinkData = {
slug: string,
title: string,
};
export type EventPermissionsData = {
can_edit: boolean,
can_delete: boolean,
can_respond: boolean,
can_set_status_for_others: boolean,
can_remove_participants: boolean,
can_create_follow_up_poll: boolean,
};
export type EventShowData = {
event: App.Data.EventData,
own_status: App.Enums.ParticipationStatus | null,
accepted_count: number,
pending_count: number,
declined_count: number,
participants: App.Data.ParticipationData[],
own_claims: App.Data.EventClaimData[],
lists: App.Data.EventLinkData[],
follow_up_polls: App.Data.EventLinkData[],
child_events: App.Data.EventSummaryData[],
permissions: App.Data.EventPermissionsData,
};
export type EventSummaryData = {
slug: string,
title: string,
date: string,
starts_at: string | null,
is_past: boolean,
};
export type GroupInviteLinkData = {
url: string | null,
locked: boolean,
};
export type GroupJoinData = {
token: string,
group_name: string,
locked: boolean,
member_count: number,
is_authenticated: boolean,
needs_email: boolean,
};
export type GroupSettingsPermissionsData = {
can_rename: boolean,
can_switch_option: boolean,
can_change_roles: boolean,
can_delete: boolean,
};
export type ParticipationData = {
user_id: number,
name: string | null,
status: App.Enums.ParticipationStatus | null,
};
export type SharedAuthData = {
user: App.Data.SharedUserData | null,
};
export type SharedFlashData = {
success: string | null,
error: string | null,
};
export type SharedPropsData = {
name: string,
auth: App.Data.SharedAuthData,
locale: App.Enums.Locale,
flash: App.Data.SharedFlashData,
};
export type SharedUserData = {
id: number,
name: string | null,
email: string | null,
language: App.Enums.Locale | null,
email_verified_at: string | null,
created_at: string | null,
updated_at: string | null,
};
}
namespace Enums {
export type GroupJoinMethod = 'invite' | 'link';
export type GroupRole = 'owner' | 'admin' | 'member';
export type Locale = 'en' | 'de';
export type ParticipationStatus = 'accepted' | 'pending' | 'declined';
export type PollGranularity = 'day' | 'hour';
export type PollResponseStatus = 'yes' | 'no' | 'maybe';
export type PollType = 'date_finder' | 'attendance';
}
}
declare namespace Illuminate {
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
declare namespace Spatie {
namespace LaravelData {
export type CursorPaginatedDataCollection<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
}

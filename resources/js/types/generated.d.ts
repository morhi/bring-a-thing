declare namespace App {
namespace Data {
export type AttendanceDayData = {
option_id: number,
poll_slug: string,
poll_title: string,
date: string,
starts_at: string | null,
label: string | null,
available_user_ids: number[],
};
export type AttendanceOptionData = {
id: number,
poll_title: string,
date: string,
starts_at: string | null,
label: string | null,
};
export type ClaimTargetData = {
user_id: number,
name: string | null,
};
export type CommentData = {
id: number,
body: string,
user_id: number,
user_name: string | null,
created_at: string,
can_delete: boolean,
};
export type CustomFieldData = {
id: number,
slug: string,
name: string,
};
export type CustomFieldValueData = {
custom_field_id: number,
value: string | null,
};
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
follow_up_audience_count: number,
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
export type PollData = {
slug: string,
title: string,
type: App.Enums.PollType,
granularity: App.Enums.PollGranularity,
uses_time_slots: boolean,
is_closed: boolean,
closed_at: string | null,
group_slug: string,
group_name: string,
organizer_id: number,
organizer_name: string | null,
source_event: App.Data.EventSummaryData | null,
options: App.Data.PollOptionData[],
};
export type PollFormData = {
group_slug: string,
group_name: string,
member_count: number,
source_event: App.Data.EventSummaryData | null,
poll: App.Data.PollData | null,
};
export type PollOptionData = {
slug: string,
date: string,
starts_at: string | null,
ends_at: string | null,
label: string | null,
yes_count: number,
maybe_count: number,
no_count: number,
is_best: boolean,
is_fixed: boolean,
has_answers: boolean,
is_schedule_locked: boolean,
can_remove: boolean,
event_slug: string | null,
};
export type PollPermissionsData = {
can_edit: boolean,
can_delete: boolean,
can_respond: boolean,
can_fix: boolean,
can_close: boolean,
can_reopen: boolean,
};
export type PollRespondentData = {
user_id: number,
name: string | null,
answers: Record<string, App.Enums.PollResponseStatus>,
is_complete: boolean,
};
export type PollShowData = {
poll: App.Data.PollData,
respondents: App.Data.PollRespondentData[],
audience_count: number,
own_answers: Record<string, App.Enums.PollResponseStatus>,
best_option_slugs: string[],
show_no_counts: boolean,
fixed_events: App.Data.EventSummaryData[],
permissions: App.Data.PollPermissionsData,
};
export type RosterClaimData = {
user_id: number | null,
user_name: string,
quantity: number | null,
};
export type RosterData = {
slug: string,
title: string,
description: string | null,
date: string | null,
is_past: boolean,
placement: App.Enums.RosterPlacement,
group: App.Data.RosterParentData | null,
event: App.Data.RosterParentData | null,
owner_id: number,
owner_name: string | null,
members_can_add_items: boolean,
attendance_poll_option_id: number | null,
attendance_day: App.Data.AttendanceDayData | null,
share_url: string | null,
};
export type RosterFormData = {
roster: App.Data.RosterData | null,
groups: App.Data.RosterParentData[],
group: App.Data.RosterParentData | null,
event: App.Data.RosterParentData | null,
custom_fields: App.Data.CustomFieldData[],
attendance_options: App.Data.AttendanceOptionData[],
can_delete: boolean,
can_duplicate: boolean,
};
export type RosterItemData = {
id: number,
slug: string,
name: string,
quantity: number | null,
unit: string | null,
notes: string | null,
date: string | null,
effective_date: string | null,
is_past: boolean,
is_task: boolean,
claimed_quantity: number,
remaining_quantity: number | null,
is_fully_claimed: boolean,
claims: App.Data.RosterClaimData[],
custom_field_values: App.Data.CustomFieldValueData[],
attendance_day: App.Data.AttendanceDayData | null,
attendance_warning: boolean,
can_claim: boolean,
comments: App.Data.CommentData[],
};
export type RosterParentData = {
id: number,
slug: string,
title: string,
};
export type RosterPermissionsData = {
can_edit: boolean,
can_delete: boolean,
can_duplicate: boolean,
can_add_items: boolean,
can_claim: boolean,
needs_acceptance: boolean,
can_manage_claims: boolean,
};
export type RosterShowData = {
roster: App.Data.RosterData,
items: App.Data.RosterItemData[],
custom_fields: App.Data.CustomFieldData[],
comments: App.Data.CommentData[],
claim_targets: App.Data.ClaimTargetData[],
attendance_options: App.Data.AttendanceOptionData[],
is_all_taken: boolean,
permissions: App.Data.RosterPermissionsData,
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
export type SharedRosterData = {
title: string,
description: string | null,
date: string | null,
is_past: boolean,
can_add_items: boolean,
is_all_taken: boolean,
custom_fields: App.Data.CustomFieldData[],
items: App.Data.RosterItemData[],
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
export type RosterPlacement = 'standalone' | 'group' | 'event';
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

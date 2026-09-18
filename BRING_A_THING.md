# Bring A Thing

"Bring A Thing" helps groups organize who brings what to an event. It combines flexible item lists with a Doodle-like scheduling and attendance tool, built on Laravel 13, Inertia 3, Vue 3, PrimeVue 4, and local WebSockets (Laravel Reverb) for real-time collaboration.

## Stack

- **Backend**: Laravel 13
- **Frontend**: Inertia 3 + Vue 3 (`<script setup lang="ts">`)
- **UI components & styling**: PrimeVue 4
- **Real-time**: Laravel Reverb (self-hosted WebSockets) + Laravel Echo on the client

---

## 1. Authentication

- **Passwordless-first**: a user registers with just an email address and receives a magic link for login. No password is required to get started.
- **Optional password**: a user may set a password later, in account settings, as an alternative login method. Magic links remain available afterward regardless of whether a password is set.
- **Shadow accounts**: inviting an email address to a group or list immediately creates a user record for that email (not a pending placeholder). The invitee appears as a member right away and receives their own magic link to manage participation (claim items, vote on polls, comment) without going through an explicit registration step.
- **Unified entry point**: there is no separate login/register page. A public marketing page (`/`) carries a single progressive form (email → name if needed → password if the account has one → optional first-list name for brand-new users) that handles both login and registration through the same request.
- **Dashboard**: a user's dashboard surfaces every group and list they own, are a member of, or have claimed an item on.

---

## 2. Groups & Lists

- A **group** has a name and a set of members (registered or shadow users, invited by email). A group can have any number of attached lists and/or polls.
- A **list** has a title, a description, an optional link to a group (a list can be fully standalone), and a set of items.
- A list may optionally carry a date, or leave it unset. Dates can instead be set per item (see §3), so date handling is fully flexible and never required.
- **Roles**: owner + admin + member model, at the group level.
  - **Owner**: the creator of a group or standalone list. Full control — edit, delete, manage members and invites, promote/demote admins.
  - **Admin**: a group member the owner has promoted. Can invite and remove members (but not remove the owner), update the settings of any list attached to the group, and add things to a group list regardless of that list's "members can add things" setting. Cannot delete the group, or change any member's admin status.
  - **Member**: everyone else. Can view, comment, claim items, and vote on polls.
  - An owner or admin may claim or unclaim an item on behalf of any other member who can view the list, in addition to managing their own claim.
- **Duplication**: a list can be manually duplicated as a starting point for a new list (e.g., cloning last week's meal plan). No automated recurrence engine in v1.
- **Link sharing**: a list owner can turn on a shareable link for any list (standalone or group-attached). Anyone with the link can view the list and claim things without logging in first; claiming (or adding a thing, see below) opens a quick login/sign-up dialog in place on the same page, then completes automatically once authenticated, so using the app never requires accepting a group invite first. The owner can regenerate the link (invalidating the old one) or disable sharing entirely. A visitor who already has full access (owner or group member) is sent to the normal list page instead of the shared read-only view.
- **Adding things via a shared link**: the same "members can add things" setting that lets group members add things to a list also grants that ability to anyone using the list's shared link, regardless of group membership. A guest is asked to log in first (no account creation form is carried through the login step); an already-authenticated visitor gets the add-a-thing form immediately.
- **Friends**: inviting an email to a group automatically saves it as a friend of the inviter (an address-book entry pointing at that user's account, real or shadow), so it can be reused without retyping. A dedicated "Friends" settings page lists a user's saved friends, lets them add one directly by email (creating a shadow account if needed, same as a group invite), and remove entries. The group invite form offers autocomplete suggestions drawn from the inviter's friends.
- **URL identifiers**: groups, lists, list items, and custom fields are addressed in URLs by an opaque random slug rather than their incrementing database id, to prevent enumerating other users' resources by guessing sequential numbers.

---

## 3. List Items

Items are designed to be flexible enough to cover varied event types: meals, a weekly meal plan, a birthday party, a house party, food and non-food logistics, and more.

- **Core fields**: name, optional quantity + unit (e.g. "2 kg potatoes"), optional notes.
- **Custom fields**: list owners can define extra free-form fields per list (e.g. "allergens", "color") to fit the specific event.
- **Split claiming**: a single item (e.g. "10 chairs") can be partially claimed by multiple members, each specifying the quantity they bring, rather than requiring one member to claim the whole thing.
- **Per-item dates**: an item may carry its own date, independent of (and overriding) the list's date. This supports patterns like a single list containing "Meal Monday", "Meal Tuesday", "Meal Wednesday" as separate items each tied to a different day, or a list with mostly date-agnostic items plus one item pinned to a specific day.
- **Attendance-aware claiming**: a list or item can be explicitly linked to a specific day of an attendance poll (§4) on the same group, via a picker on the list/item settings (an item's own link overrides its list's, mirroring the per-item date override). This never blocks claiming outright (deviation from the original design, confirmed on request): a member who answered "maybe" or "yes" for that day sees the normal claim controls, while a member who answered "no" or hasn't responded at all still gets the full claim controls, plus a warning tag noting they haven't confirmed availability for that day. The intent is to inform, not gate.
- **Comments**: each item can carry its own short comment thread, in addition to the list-level thread (§5).
- **Lifecycle**: no explicit status/lifecycle field in v1. "Past" vs. "upcoming" is derived purely from whether the linked date (if any) has passed.

---

## 4. Polls (Doodle-like scheduling)

Two distinct poll types, both calendar-based and attached to a group:

### Date finder poll
The classic Doodle use case. An organizer proposes candidate days and/or time slots. Each member votes yes/no/maybe per option. Used to converge on one or more chosen meeting dates or times.

### Attendance poll
Tracks who is around when, across a date range (e.g. a week-long group stay). An organizer defines a date range (internally a set of individual days, or hour-based slots depending on poll mode). Each member independently marks their own per-day (or per-slot) availability (yes/no/maybe). Unlike a date finder poll, this does not converge on one answer — it stays open and reflects ongoing changes in attendance over time.

### Shared behavior
- **Granularity** (day-based vs. hour-based) is a setting on the poll, not a hardcoded distinction between the two types.
- Votes update live via WebSockets, so all viewers see responses as they come in.
- Lists and items can reference a specific day from an attendance poll to gate item-claim eligibility by that day's attendance (§3).
- **Closing a poll** (added on request, beyond the original scope of this section): the organizer or a group admin can close a poll to stop accepting new votes, and reopen it again at any time. A date finder poll must name one of its own options as the chosen result when closed, since it otherwise has no explicit "decision" beyond the live vote-count-derived "Best" indicator; an attendance poll has no single winner and simply freezes. This is the only status/lifecycle-style field anywhere in v1 — everything else in the app (rosters, items, other polls) is still deliberately status-free (§3).

---

## 5. Comments

- **List-level**: a general discussion thread visible to all group/list members.
- **Item-level**: a shorter, scoped thread on a specific item (e.g. "can bring a gluten-free version instead?").
- New comments broadcast live to active viewers.

---

## 6. Real-time (Laravel Reverb)

Real-time channels cover:

- Live item claim/unclaim updates, including partial/split claims.
- Live comments, at both list and item level.
- Live poll voting, for both poll types.
- Presence indicators — avatars of members currently viewing a given list or poll.

---

## 7. Notifications

- **In-app notification center**: a bell/list in the UI, updated live via WebSockets, covering new invites, new comments, poll activity, and item claims relevant to the user.
- **Email**: the magic-link login email is the only transactional email in v1. Invites, comments, and reminders surface in-app rather than via separate emails.

---

## 8. Architecture

### Backend
- Laravel 13, feature-organized (Actions/Services layer where logic exceeds simple CRUD).
- Laravel Reverb for broadcasting.
- Queued jobs for magic-link email dispatch.

### Core models
| Model | Purpose |
|---|---|
| `User` | Registered or shadow account; nullable password. |
| `Group` | Container for members, lists, and polls. |
| `GroupMember` | Pivot: user ↔ group, with role (`owner`/`admin`/`member`). |
| `Roster` | A list (named `Roster` to avoid the `List` reserved word); title, description, nullable group, nullable date, nullable share token for link sharing. |
| `Friend` | A user's saved reference to another user's account, for reuse across invites. |
| `ListItem` | Item on a list; name, quantity, unit, notes, nullable date (overrides list date). |
| `ItemClaim` | A member's (partial) claim on an item; quantity claimed. |
| `CustomField` | Per-list custom field definition. |
| `ItemCustomFieldValue` | Per-item value for a `CustomField`. |
| `Comment` | Polymorphic; attaches to a list or an item. |
| `Poll` | `type` (`date_finder`/`attendance`), `granularity` (`day`/`hour`), attached to a group; `closed_at` and (date finder only) `chosen_option_id` track closing (added on request). |
| `PollOption` | A candidate day/slot on a poll. |
| `PollResponse` | A member's yes/no/maybe on a `PollOption`. |
| `Notification` | Laravel's built-in notifications table (database + broadcast channels). |

### Frontend
- Inertia 3 + Vue 3, `<script setup lang="ts">` throughout.
- PrimeVue 4 is the UI foundation for the entire app: all screens are built from PrimeVue components (forms, dialogs, tables/data views, calendar, buttons, menus, toasts, etc.) and its theming system, rather than hand-rolled markup or a utility-CSS layer.
- Laravel Echo + Reverb client for subscribing to private/presence channels.

### Broadcasting channels
- `list.{id}` — private channel per list, for claims and comments.
- `poll.{id}` — private channel per poll, for votes.
- Presence channels per list/poll — active-viewer avatars.

### Auth
- Signed-URL-based magic-link tokens issuing a session, implemented without Fortify's default password-first flow.
- Optional password login layered on top of the same `User` model once a password is set.

---

## Out of scope (v1)

- Automated recurring lists (manual duplication only).
- Transactional emails beyond the magic-link login email.
- Explicit list/item status fields (derived from date instead).

---

## Implementation instruction

Before implementing any feature against Laravel 13, Inertia 3, Vue 3, PrimeVue 4, or Laravel Reverb, read the official documentation for the exact version in use (via Laravel Boost, if available, or the web) rather than relying on prior knowledge. APIs across these packages change between major versions, and outdated assumptions must not drive implementation decisions.

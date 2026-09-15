# Bring A Thing — Implementation Plan

This plan breaks the specification in [BRING_A_THING.md](BRING_A_THING.md) into sequential, dependency-ordered phases. Each phase is scoped to a working, tested, committed increment: no phase starts until the previous one is green and merged into `main`.

## UI structure (pages, navigation, layout)

This is a lightweight page and navigation inventory, not a visual mockup. It exists so Phase 0's base layout does not need to be reworked once later phases add real pages. Layout details (spacing, exact component choices) are decided per-phase against the current PrimeVue 4 docs, not fixed here.

**Layouts**
- `GuestLayout`: unauthenticated pages (login, magic-link consumption). Minimal, no navigation.
- `AppLayout`: authenticated pages. Topbar with app name, notification bell, user menu (account settings, logout). No permanent sidebar in v1; group/list/poll context navigation lives on the entity's own show page, not in a global sidebar, since groups/lists/polls are peers rather than a strict hierarchy (a list can be standalone).

**Pages (route → purpose → primary phase)**
| Route | Purpose | Phase |
|---|---|---|
| `/login` | Request magic link, or log in with password if set | 1 |
| `/login/{token}` | Consume signed magic-link token, start session | 1 |
| `/register` | Email-only registration | 1 |
| `/settings` | Account settings: set/change password | 1 |
| `/` (dashboard) | Groups, lists, and claimed items the user owns/is a member of/has claimed on | 1, 3 |
| `/groups/{group}` | Group show: members, invite form, attached lists, attached polls | 2 |
| `/groups/{group}/edit` | Owner-only group settings | 2 |
| `/lists/{list}` | List show: metadata, items (with claim controls), list-level comment thread | 3, 4, 6 |
| `/lists/{list}/edit` | Owner-only list settings, custom field management | 3, 4 |
| `/polls/{poll}` | Poll show: calendar/grid of options, vote controls, (for date finder) converged result | 5 |

**Not separate routes (modals / inline UI instead)**
- Create group, create list, create poll: modal or inline form from the dashboard/group/list context, not a dedicated `/…/create` page, to keep navigation flat.
- Item detail (custom fields, item-level comments, claim breakdown): expands inline or in a drawer on the list page, not a separate route, so claiming stays a single-page action.
- Notifications: dropdown from the topbar bell, not a dedicated page, per §7 (in-app only, no separate inbox specified).

**Decided:**
- Attendance/date-finder poll grid: a custom calendar-style grid (week/calendar layout, closer to Doodle), not a PrimeVue DataTable. Built in Phase 5.
- Dashboard: separate "Groups" and "Standalone lists" sections (not a mixed feed). Built in Phase 1/3.

## Working method (applies to every phase)

1. Read the current version of the relevant Laravel 13 / Inertia 3 / Vue 3 / PrimeVue 4 / Reverb documentation before writing code against it, per the Implementation instruction in `BRING_A_THING.md`.
2. Implement the phase in small, reviewable steps.
3. Write Pest feature tests for backend behavior as it is built, not after. Add browser verification (manual or Dusk-style) for any user-facing Inertia/Vue screen before marking the phase done.
4. Run `php artisan test --compact` (narrow filter while iterating, full suite before closing the phase) and `npm run check`.
5. Run `vendor/bin/pint --dirty --format agent` on touched PHP files.
6. Commit with a short, descriptive message per logical unit of work (not one giant commit per phase).
7. Update this file: check off the phase, note the commit hash(es) and date, note any deviations from the plan.

Status legend: `[ ]` not started, `[~]` in progress, `[x]` done.

---

## Phase 0 — Foundation
**Goal:** project boots, base layout and tooling work, no feature logic yet.

- [x] Confirm Laravel 13 / Inertia 3 / Vue 3 / PrimeVue install is healthy (`ddev artisan about`, `npm run dev`).
- [x] Set up Laravel Reverb (`ddev artisan install:broadcasting` or equivalent per current docs) and Echo client config, verified with a throwaway test event.
- [x] Base Inertia layout (`resources/js/layouts`) using PrimeVue theming, navigation shell, toast/notification placeholder.
- [x] Base Pest test setup confirmed working (`ddev artisan test --compact`).

**Tests:** one smoke feature test hitting `/`, one manual browser check that the layout renders with PrimeVue styling.
**Commit:** `chore: configure Reverb broadcasting and base Inertia/PrimeVue layout`

---

## Phase 1 — Authentication (§1)
**Goal:** passwordless magic-link auth, optional password, shadow accounts, dashboard shell.

- [x] `User` model: nullable `password`, migration reviewed for shadow-account fields.
- [x] Magic-link issuance: signed URL token, queued mail job for the login link.
- [x] Magic-link consumption: signed-URL controller/action that starts a session.
- [x] Registration by email only (no password required).
- [x] Optional password: account settings page to set/change a password; login form accepts password as an alternative to magic link.
- [x] Shadow account creation path: helper/service to find-or-create a `User` by email (used later by invites), triggers a magic link, does not require a name/password.
- [x] Dashboard page (Inertia/Vue): lists groups/lists the user owns, is a member of, or has claimed an item on (placeholder empty states until Phase 2/3 exist).

**Tests:** Pest feature tests for magic-link request/consume, expired/invalid token, optional password set + password login, shadow-user creation idempotency (inviting same email twice reuses the user). Browser check: request a magic link, follow it, land on dashboard.
**Commit(s):** e.g. `feat: add passwordless magic-link authentication`, `feat: add optional password login`, `feat: add shadow account creation`, `feat: add dashboard shell`

**Deviations:** Also added a `name`/`email`-initial avatar fallback and a user menu (account settings, logout) in `AppLayout`, since the layout plan called for it and shadow accounts have no name. `tests/Feature/ExampleTest.php` was repurposed into guest-redirect/dashboard-access tests since it referenced the now-removed `home` route; `Welcome.vue` was removed as unused.

Registration originally collected only email, per the spec's "email-only registration" wording, but that left shadow/self-registered accounts showing up by email indefinitely with no way to add a name (reported during Phase 2). Registration and magic-link requests now require a name whenever the resulting account would otherwise have none; Account Settings also gained a Profile section to set/change it later. See `.ai/rules` and the Phase 2 section below for the invite-side counterpart.

---

## Phase 2 — Groups & Members (§2, partial)
**Goal:** groups exist with owner/member roles and email-based invites (shadow accounts wired in).

- [x] `Group`, `GroupMember` (pivot with `role`) models + migrations.
- [x] Create/edit/delete group (owner only), authorization via Policy.
- [x] Invite member by email: reuses Phase 1 shadow-account helper, sends magic link, adds `GroupMember` with role `member`.
- [x] Group show page (Vue): member list, invite form, owner-only management controls.
- [x] Dashboard now lists real groups.
- [x] Invitation-pending status per member (added on request, beyond original phase scope): `group_user.accepted_at`, cleared by a `Login` event listener on the invitee's first login; shown as an "Invitation pending" tag.
- [x] Owner can remove a non-owner member (added on request); the owner cannot be removed and can only delete the whole group.

**Tests:** Pest feature tests for group CRUD authorization (owner vs. member vs. non-member), invite flow creates/reuses a `User` and `GroupMember`, invite-acceptance-on-login, member removal authorization. Browser check: create a group, invite an email, confirm it appears as a member with a pending tag; remove a member.
**Commit(s):** `feat: add groups with owner/member roles and email invites`, `feat: add group show/settings pages and dashboard integration`, `feat: add invitation-pending status and member removal for groups`

**Deviations:** `role` is stored as a backed PHP enum (`App\Enums\GroupRole`) rather than a plain string. Group creation uses `Group::make()` + explicit `owner_id` assignment instead of mass-assignment, since `owner_id` is intentionally excluded from `Group`'s `#[Fillable]` list (it must never be settable from request input). The owner is also added as a `GroupMember` row with role `Owner`, so `members()` includes the owner and the `view` policy check is a single membership query. No "leave group" or member-removal UI yet; out of scope for this phase per the spec's owner/member CRUD focus.

---

## Phase 3 — Lists (§2 continued)
**Goal:** standalone or group-attached lists exist, without items yet.

- [ ] `Lst` model + migration (title, description, nullable `group_id`, nullable date).
- [ ] Create/edit/delete list (owner only), Policy covering standalone vs. group-scoped access.
- [ ] List show page (Vue) with title/description/date, empty item area.
- [ ] Manual list duplication action (clones list metadata; item duplication added once items exist in Phase 4).
- [ ] Dashboard now lists real lists.

**Tests:** Pest feature tests for list CRUD + authorization, standalone list visible only to its owner, group list visible to group members, duplication creates an independent copy. Browser check: create a standalone list and a group list, duplicate one.
**Commit(s):** `feat: add lists (standalone and group-attached)`

---

## Phase 4 — List Items & Claiming (§3)
**Goal:** the core value proposition — items, custom fields, split claiming.

- [ ] `ListItem` model + migration (name, quantity, unit, notes, nullable date).
- [ ] `CustomField` + `ItemCustomFieldValue` models; list owner can define/manage custom fields; items expose them on create/edit.
- [ ] `ItemClaim` model (member, item, quantity claimed); enforce total claimed quantity ≤ item quantity when a quantity is set.
- [ ] Claim/unclaim/partial-claim UI on the item (Vue + PrimeVue components), including the "who claimed how much" display.
- [ ] Item-level comment thread (depends on Phase 6's `Comment` model — stub or sequence Phase 6 before this sub-task if needed; see note below).
- [ ] Extend list duplication to clone items and custom fields (not claims).
- [ ] Derived past/upcoming display based on item date vs. list date vs. now.

**Note:** item comments depend on the polymorphic `Comment` model from Phase 6. If Phase 6 is not yet done, ship Phase 4 without the comment thread and wire it in during Phase 6 instead of blocking on it.

**Tests:** Pest feature tests for item CRUD, custom field save/display, split-claim math (over-claim rejected, exact and partial claims accepted, unclaim), duplication behavior. Browser check: add an item with a custom field, claim part of it as two different members.
**Commit(s):** `feat: add list items with custom fields`, `feat: add split item claiming`

---

## Phase 5 — Polls: Date Finder & Attendance (§4)
**Goal:** both poll types on a shared data model, live voting deferred to Phase 7.

- [ ] `Poll` (`type`, `granularity`), `PollOption`, `PollResponse` models + migrations.
- [ ] Organizer flow: create a poll on a group, define candidate days/slots (date finder) or a date range expanded into day/slot options (attendance).
- [ ] Voting flow: member sets yes/no/maybe per option; attendance polls stay open and re-editable, date finder polls converge (results view highlighting best option(s)).
- [ ] Poll show page (Vue) with a calendar/grid view via PrimeVue components.

**Tests:** Pest feature tests for poll creation (both types), response upsert (changing a vote updates rather than duplicates), authorization (group members only). Browser check: create an attendance poll, vote as two members, confirm the grid reflects both.
**Commit(s):** `feat: add date finder and attendance polls`

---

## Phase 6 — Attendance-Aware Claiming & Comments (§3 gating, §5)
**Goal:** wire the cross-cutting pieces that depend on both Phase 4 and Phase 5.

- [ ] Link a list or item date to a specific attendance-poll day; when linked, gate claim eligibility by that member's yes/no/maybe for the day (unavailable members shown as "away", not offered a claim control).
- [ ] `Comment` model (polymorphic: list or item) + migration.
- [ ] List-level comment thread UI; item-level comment thread UI (fulfills the Phase 4 note above).

**Tests:** Pest feature tests for gating logic (available/unavailable/no-poll-linked cases), comment CRUD + authorization on both list and item. Browser check: link an item to an attendance day, confirm an unavailable member sees "away" instead of a claim button; post a comment at list and item level.
**Commit(s):** `feat: gate item claims by attendance poll availability`, `feat: add list and item comments`

---

## Phase 7 — Real-time (§6)
**Goal:** everything built so far becomes live via Reverb.

- [ ] Private channel `list.{id}`: broadcast claim/unclaim (including partial claims) and comment events.
- [ ] Private channel `poll.{id}`: broadcast vote events for both poll types.
- [ ] Presence channels per list/poll: active-viewer avatars.
- [ ] Frontend: Echo subscriptions in the relevant Vue pages, live-updating state without a full reload.

**Tests:** Pest/Laravel broadcasting tests asserting the correct events fire on claim/comment/vote with correct channel + payload. Browser check: two browser sessions (or one + incognito), confirm a claim/comment/vote in one appears live in the other, and presence avatars update.
**Commit(s):** `feat: broadcast live claims and comments`, `feat: broadcast live poll votes and presence`

---

## Phase 8 — Notifications (§7)
**Goal:** in-app notification center; confirm the magic-link email remains the only transactional email.

- [ ] Laravel notifications (database + broadcast channel) for: new invite, new comment (list/item, relevant to the user), poll activity, item claims relevant to the user.
- [ ] Notification bell UI (Vue/PrimeVue), live-updated via the broadcast channel, mark-as-read.
- [ ] Audit: confirm no other transactional emails were introduced elsewhere in the app.

**Tests:** Pest feature tests per notification trigger (invite, comment, poll activity, claim) asserting the notification is created and broadcast. Browser check: trigger each notification type as one user, confirm it appears live in another user's bell.
**Commit(s):** `feat: add in-app notification center`

---

## Phase 9 — Hardening & Full Regression
**Goal:** close out v1 against the spec.

- [ ] Full pass over Policies for every model (owner/member boundaries, standalone-list access, shadow-user edge cases).
- [ ] Full `php artisan test --compact` run, full `npm run check`.
- [ ] Manual end-to-end browser walkthrough covering every section of `BRING_A_THING.md` in one continuous scenario (group → invite → list → items → claims → poll → attendance-gated claim → comments → live updates across two sessions → notifications).
- [ ] Confirm all "Out of scope (v1)" items were in fact not built (no recurrence engine, no extra transactional emails, no status field, no extra roles).

**Commit:** `chore: v1 regression pass` (only if fixes were needed; otherwise no commit)

---

## Progress log

| Phase | Status | Commit(s) | Date | Notes |
|---|---|---|---|---|
| 0 | [x] | | 2026-09-15 | Backend/build/tests verified; browser-confirmed PrimeVue styling renders correctly |
| 1 | [x] | | 2026-09-16 | See commits below |
| 2 | [x] | 49ff9aa, 1b99076 | 2026-09-16 | See commits above |
| 3 | [ ] | | | |
| 4 | [ ] | | | |
| 5 | [ ] | | | |
| 6 | [ ] | | | |
| 7 | [ ] | | | |
| 8 | [ ] | | | |
| 9 | [ ] | | | |

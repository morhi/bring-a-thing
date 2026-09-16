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
| `/` (dashboard) | Groups, rosters, and claimed items the user owns/is a member of/has claimed on | 1, 3 |
| `/groups/{group}` | Group show: members, invite form, attached rosters, attached polls | 2 |
| `/groups/{group}/edit` | Owner-only group settings | 2 |
| `/rosters/{roster}` | Roster show: metadata, items (with claim controls), roster-level comment thread | 3, 4, 6 |
| `/rosters/{roster}/edit` | Owner-only roster settings, custom field management | 3, 4 |
| `/polls/{poll}` | Poll show: calendar/grid of options, vote controls, (for date finder) converged result | 5 |

**Not separate routes (modals / inline UI instead)**
- Create group, create roster, create poll: modal or inline form from the dashboard/group/roster context, not a dedicated `/…/create` page, to keep navigation flat.
- Item detail (custom fields, item-level comments, claim breakdown): expands inline or in a drawer on the roster page, not a separate route, so claiming stays a single-page action.
- Notifications: dropdown from the topbar bell, not a dedicated page, per §7 (in-app only, no separate inbox specified).

**Decided:**
- Attendance/date-finder poll grid: a custom calendar-style grid (week/calendar layout, closer to Doodle), not a PrimeVue DataTable. Built in Phase 5.
- Dashboard: separate "Groups" and "Standalone rosters" sections (not a mixed feed). Built in Phase 1/3.
- List entity naming: the model class is `Roster` (not `List`, a reserved PHP word); routes, UI copy, and docs all say "roster" to match. Decided in Phase 3.

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

## Phase 3 — Rosters (§2 continued)
**Goal:** standalone or group-attached rosters (lists) exist, without items yet.

- [x] `Roster` model + migration (title, description, nullable `group_id`, nullable date).
- [x] Create/edit/delete roster (owner only for edit/delete; any group member may attach a new roster to a group they belong to), Policy covering standalone vs. group-scoped access.
- [x] Roster show page (Vue) with title/description/date, empty item area.
- [x] Manual roster duplication action, owner only (clones title/description, clears the date; item duplication added once items exist in Phase 4).
- [x] Dashboard now lists real standalone rosters; Group show page lists real attached rosters with a create-roster modal.

**Tests:** Pest feature tests for roster CRUD + authorization, standalone roster visible only to its owner, group roster visible to group members, duplication creates an independent copy with a cleared date, rosters cascade-delete with their group. Browser check: create a standalone roster and a group roster, edit the date via DatePicker, duplicate one, delete a group and confirm its roster is gone.
**Commit(s):** `feat: add rosters (standalone and group-attached)`, `feat: add roster show/edit pages and dashboard/group integration`

**Deviations:** The model is named `Roster`, not `Lst`/`List` — `List` is a reserved PHP word, so following a request to rename the original `Lst` workaround, the model, controller, policy, factory, and relations all use `Roster`, and this was then extended to routes (`/rosters/{roster}`), route names (`rosters.*`), the Vue page folder (`Rosters/`), and UI copy ("New roster", "Roster created.") for full consistency. Duplicating a roster clears its date rather than keeping it (decided during this phase, since a cloned roster like "last week's meal plan" is meant as a template for a new, not-yet-decided date) and is restricted to the roster's owner. Deleting a group cascades to delete its attached rosters (`group_id` foreign key `cascadeOnDelete`).

---

## Phase 4 — List Items & Claiming (§3)
**Goal:** the core value proposition — items, custom fields, split claiming.

- [x] `RosterItem` model + migration (name, quantity, unit, notes, nullable date). Named `RosterItem` (not `ListItem`), matching the Phase 3 `Roster` naming decision.
- [x] `CustomField` + `RosterItemCustomFieldValue` models; roster owner can define/manage custom fields (Roster settings page); items expose them on create/edit.
- [x] `RosterItemClaim` model (member, item, quantity claimed); enforce total claimed quantity ≤ item quantity when a quantity is set; unquantified items accept one exclusive claimant.
- [x] Claim/unclaim/partial-claim UI on the item (Vue + PrimeVue components), including the "who claimed how much" display.
- [ ] Item-level comment thread — still deferred to Phase 6 as planned (depends on the polymorphic `Comment` model).
- [x] Extend roster duplication to clone items and custom fields (not claims).
- [x] Derived past/upcoming display based on item date vs. roster date vs. now (`RosterItem::is_past` accessor).

**Note:** item comments depend on the polymorphic `Comment` model from Phase 6. If Phase 6 is not yet done, ship Phase 4 without the comment thread and wire it in during Phase 6 instead of blocking on it.

**Tests:** Pest feature tests for item CRUD + authorization, custom field save/display, split-claim math (over-claim rejected, exact and partial claims accepted, unclaim, update-own-claim), duplication behavior (items/fields cloned, claims not), past/upcoming derivation, and broadcasting authorization on the roster private/presence channels. Browser-verified: claim/update-claim/unclaim, item edit, custom field add + display in the item form, and the `members_can_add_items` toggle, all against a real ddev + Reverb setup with no console errors.
**Commit(s):** _not yet committed_

**Deviations:**
- Per user request, a roster gained a `members_can_add_items` boolean (Roster settings page) so a group-attached roster can opt into letting any group member add items, not just the owner; owners can always add/edit/delete items, and only owners can edit/delete regardless of who added an item.
- Per user request, real-time collaboration was pulled forward from Phase 7 for the parts built in this phase: a private `roster.{id}` channel broadcasts item create/update/claim changes (`RosterItemSaved`) and deletions (`RosterItemDeleted`), and a `presence.roster.{id}` presence channel drives active-viewer avatars on the roster page. Poll-vote broadcasting and comment broadcasting remain in Phase 7/6 since those features don't exist yet.
- Custom field values are free-text only (no typed fields), per explicit decision — matches the spec's "extra free-form fields" wording.
- Reverb needed local-environment wiring to be reachable from the browser under ddev: `.ddev/config.yaml` gained a `web_extra_daemons` entry running `reverb:start` and a `web_extra_exposed_ports` entry exposing it over TLS via ddev-router; `.env`'s client-facing `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` now point at `bring-a-thing.ddev.site:8443` over https instead of `localhost:8080`/http (the server-side bind stays `0.0.0.0:8080`, unaffected). This is local-environment config only, not application code.
- Found and fixed two bugs during manual verification: (1) `RosterController::edit` wasn't eager-loading the `group` relation, so the `members_can_add_items` checkbox never rendered for group rosters; (2) the roster show page's "grouped by date" view (used whenever any item has an effective date) was a stub with no claim/edit/remove controls at all — only the flat, no-dates view had them. Fixed by extracting the full item card into `resources/js/components/RosterItemCard.vue`, used by both views, so claim/edit/remove now work regardless of whether the roster/items carry dates.

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

- [x] Private channel `roster.{id}`: broadcast claim/unclaim (including partial claims) — done in Phase 4, pulled forward on request.
- [ ] Private channel `roster.{id}`: broadcast comment events (once Phase 6 adds comments).
- [ ] Private channel `poll.{id}`: broadcast vote events for both poll types.
- [x] Presence channel per roster: active-viewer avatars — done in Phase 4, pulled forward on request.
- [ ] Presence channel per poll: active-viewer avatars.
- [x] Frontend: Echo subscriptions on the roster show page, live-updating state without a full reload — done in Phase 4.
- [ ] Frontend: Echo subscriptions on the poll show page.

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
| 3 | [x] | 77f65a5, 768a596 | 2026-09-16 | Backend/tests/`npm run check` verified; browser-confirmed create/edit/duplicate/cascade-delete flows |
| 4 | [x] | | 2026-09-16 | Backend/tests (`php artisan test --compact`, 91 passing) and `npm run check` verified; browser-confirmed claim/edit/remove/custom-fields/members-can-add-items flows over a working ddev+Reverb websocket setup — see Deviations for two bugs found and fixed during that check |
| 5 | [ ] | | | |
| 6 | [ ] | | | |
| 7 | [ ] | | | |
| 8 | [ ] | | | |
| 9 | [ ] | | | |

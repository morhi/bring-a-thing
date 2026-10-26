# Handoff: Bring a Thing

This file is the complete brief for a new Claude Code session in this repository. It is self-contained: everything the session needs is in this file, in `docs/requirements.md` and in `docs/prototype/`. Do not look for a "Prototypes" project or earlier chats.

The work has two stages that run strictly in order:

- **Stage A: finish the designed prototype** in `docs/prototype/` (batches 2 and 3). Touch nothing outside `docs/` in this stage.
- **Stage B: rewrite the frontend of the existing app** from the prototype, with shadcn-vue, and extend the backend where the requirements need it.

Do not start Stage B before Stage A is reviewed and approved by Maurice. Ask for that approval explicitly.

## Sources of truth

- `docs/requirements.md` (v1.11) defines behavior. Where anything else disagrees with it, the requirements win.
- `docs/prototype/` shows the approved UI concept and design system. In Stage B it is a reference for layout, content hierarchy, wording and flows, not code to copy.
- `BRING_A_THING.md` and `IMPLEMENDATION.md` describe the app as it exists today (PrimeVue frontend, backend up to Phase 6). They are history, not targets. `CLAUDE.md` asks to keep them in sync with the app; at the end of Stage B, propose to Maurice how to retire or rewrite them so that `docs/requirements.md` stays the single behavior document.

Open question 1 in the requirements (volume targets) is answered: about 5 groups per account and about 5 polls, events and lists each per group per year (requirements v1.7).

## Working rules for both stages

- Follow `CLAUDE.md` and `.ai/rules` in this repository (ddev for PHP and Artisan, npm via nvm with Node 24, Pest, Pint, Boost docs search, browser checks with Claude in Chrome when available).
- Code style: guard clauses and early returns, a blank line before every `if`, kebab-case for views and templates, comments only for logic that is not self-explanatory. No `Co-Authored-By` lines in commits.
- Commit small, descriptive units on `main`. Commit message in the style of the existing log, for example `docs: add prototype batch 2` or `feat: add events and participation`.
- Ask before deciding anything the requirements do not define. Propose changes to `docs/requirements.md` with the affected sections before and after, wait for approval, apply, raise the version.
- Interface language: the prototype and the app are bilingual (English, German). Every new string goes into both dictionaries. Content people write is never translated.
- Components page first: build every new component on a components page before it goes into a page. The page is a dev-only view that lists all shared components with their variants and states, in light and dark, at 375 px and 1280 px. Look there first and reuse or extend an existing component instead of writing a new one. Wire the component into the page that needs it only after it works in isolation.

## Stage A: finish the prototype

### Status

Batches 1 to 3 are done and committed (`docs:` prototype commits in `git log`). Open in Stage A: the handoff check and Maurice's approval. The text of the batch sections below describes the original scope; the prototype now also contains what the next section lists.

### Decisions made after batch 1

- Name stays "Bring a Thing". Copy reads "bring or take on", because lists cover any task, not only things to bring (requirements v1.4).
- Fonts: headlines Familjen Grotesk (`--font-display`), the tilted "Thing" tag and `.tag` Gabarito (`--font-tag`), body Instrument Sans.
- Depth system in `css/depth.css` and `--depth-*` tokens: three nesting levels with distinct background per level, a 2 px border on every level, a 25 px radius on level 2 boxes, in light and dark. Dark level 1 equals the form container color, level 2 is 5% lighter.
- One content width for all app views: max 1120 px, 32 px gutters, 16 px at 375 px; form fields cap at 40rem.
- Every empty state uses the dashed, hatched `EmptyState`.
- Poll edit and list edit are the create pages in edit mode, not dialogs. Creating a group has its own page with the shared `InviteField` (email or name, friend suggestions, chips, invite link).
- Pages added since batch 1: New group, join page for the invite link, notifications panel behind the bell, group settings, friends, settings.
- Kept for later (requirements section 10): QR codes, automatic deletion, list expiry, Excel export.

### What exists (batch 1, artifact "Bring a Thing", committed as `a4bb262`)

Pages: start page with login form, dashboard, group page, create pages for poll, event and list, new-group dialog. Stubs: settings, friends, poll, event, list.

Structure of `docs/prototype/`:

```
prototype.html            entry: meta, stylesheet links, #app, module script
css/tokens.css            layer 1 primitives, layer 2 semantic light/dark, layer 3 component tokens, spacing contract
css/base.css              reset, type scale, layout primitives (.container, .page, .section, .stack, .row, .grid)
css/components.css        button, field, choice, segmented, card, badge, tag, avatar, mark, rows, empty, alert, dialog, toast, feature badges
css/shell.css             header, wordmark, user menu, back link, review bar
css/forms.css             form grid, option rows, audience note, form pages with sticky action bar
css/calendar.css          option calendar (month grid, week grid, slots)
css/welcome.css, dashboard.css, group.css   one file per view
js/main.js                createApp, component registration, view switch by ui.view
js/core.js                reactive data (clone of mock data), ui state, roles, features, views registry, go(), goCreate(), toast(), query helpers h.*
js/i18n.js                t(), dictionaries en and de, date and time formatting per language, TODAY = 2026-10-25
js/mock-data.js           entities named as in requirements section 7
js/components.js          Icon, Avatar, Mark, DateBlock, EmptyState, AvatarStack, ProgressBar, FormField, ChoiceGroup, FormPage, AppDialog, LangSwitch
js/shell.js               Wordmark, AppHeader, Toasts, ReviewBar
js/dialogs.js             NewGroupDialog, StubView
js/option-calendar.js     OptionCalendar: day mode (click or drag days), slot mode (draw, move, resize slots)
js/views/welcome.js, dashboard.js, group.js, create.js
```

Runtime: Vue 3.5.13 ESM browser build from unpkg, no other dependency, no Tailwind. Plain CSS on custom properties, because the token layers mirror the later `resources/css/app.css`. Views switch through `ui.view`; `go(view, ids)` sets ids such as `currentPollId` and focuses the page `h1`. Writes go into the in-memory `data` object so every flow works for a session.

To view it: serve the folder over HTTP (for example `npx serve docs/prototype` or `python3 -m http.server` inside it) and open `prototype.html`. The file has no `<html>` skeleton on purpose (the artifact host wraps it); for local viewing, wrap it in a minimal doctype, charset and viewport meta, or open it through the artifact.

Conventions to keep:

- Every form block is a `FormField` (label, control, error or hint) on the spacing tokens `--space-label` (8 px), `--space-stack` (16 px), `--space-inner` (8 px). Radio cards are a `ChoiceGroup`. Progress bars are `ProgressBar`. Icons use `.icon-sm/.icon-md/.icon-lg`, never inline styles.
- Every element that implements a feature carries `data-feature="F-xx"`. The review bar (bottom right) switches role (owner, admin, member), view state, theme, language and feature badges. New views register their states in `views` in `core.js` and read them with `viewState()`.
- Colors only through semantic tokens. WCAG AA pairs: light mode primary emerald-700 on white, dark mode emerald-400 with emerald-950 text; emerald-500 only as a surface without text. Textures (dots on the page ground, hatch on empty states) never sit behind running text.
- The design brief from Maurice: clean, generous spacing, modern and a little playful but not too much, light and dark mode, the emerald primary, textures that do not distract. The one playful mark is the tilted "Thing" tag; everything else stays quiet.
- Date formats follow the language: `15.10.2026` in German, `15 Oct 2026` in English, 24-hour times in both.
- Native date and time inputs are a placeholder; in Stage B they become the shadcn-vue Calendar and DatePicker.
- Mobile first from 375 px, no horizontal scrolling except inside the poll grid, keyboard access for every interaction, one `h1` per view.

### Batch 2: poll, event, list pages

Replace the three stubs. Each is a view file plus a CSS file. Read the feature texts in `docs/requirements.md` before building; the summary here only orders the work.

**Poll page** (`views/poll.js`, F-06 to F-09, F-12): header with title, group link, type and granularity badges, organizer, status (open, closed, fixed), audience note for follow-up polls (F-12). The answer grid: one column per option (date, weekday, time and label for slots), one row per respondent, the current user's row editable with a three-state control (yes, maybe, no) per option, other rows read-only, "Best option" marks on the columns with the most yes answers, ties all marked. Below the grid the per-option totals. Organizer, owner and admin actions: fix dates (F-09, multi-select of options with a confirmation that lists the events it will create and the derived participation), close and reopen (F-08), settings (rename, add option, remove option with its responses, delete). Attendance polls have no best option and no fix action. States: closed (read-only, fixed options highlighted), no responses yet, not in audience ("Not found", section 8).

**Event page** (`views/event.js`, F-10 to F-13): header with title, date block, time, location, group, "From a poll" link when it has an origin, parent event link for child events. Participation block: own status with Accept and Decline, counts and avatar lists for accepted, pending, declined; decline with claims opens a confirmation listing the claims that get released (F-11). Organizer, owner and admin: edit (date change resets acceptances to pending), delete with confirmation, set status for others. Sections: lists of the event (only for participants), follow-up polls with "New follow-up poll" (goes to the create poll page with `eventId` in `ui.formContext`), child events. States: past event (no accept or decline, lists readable), no acceptances yet, member who declined (key details only).

**List page** (`views/list.js`, F-13 to F-20): header with title, description, date, placement (standalone, group, event), attendance day link (F-18) when set, owner actions (settings page or dialog for title, description, date, "members can add items", custom fields, attendance link, share link, duplicate, delete). Items grouped by date when any item has one; each item with name, quantity and unit, notes, custom field values, claims (who and how much), the remaining amount, and the claim controls: claim (task), claim with quantity (partial), change or remove own claim, owner and admin claim-for and remove-claim. Attendance warning with "Claim anyway" confirmation for people who answered no or nothing for the linked day (F-18). Pending participants on an event list see the note to accept first (F-15). Comments on the list and per item with Cmd/Ctrl+Enter (F-20). Add item (dialog, F-14) for whoever may add. States: empty, all taken, over-claim rejected, guest view through the share link (F-19) with the in-place login.

Mock data already contains polls p1 to p5, events e1 to e4, lists l1 to l4 with items and claims, enough for every state. Extend it where a state has no data.

Verify each batch in the browser at 1280 px and 375 px, light and dark, English and German, with no console errors, and click every flow once. Commit the batch.

### Batch 3: remaining pages and the handoff check

Settings (F-02 with the language switch, F-24), friends (F-05), share-link guest view if not done in batch 2 (F-19), notifications panel behind the bell (F-23), group settings (F-03: name, roles, delete). Live updates (F-21) are out of scope for the prototype; note them as a stub.

Then the handoff check: every feature F-01 to F-24 has at least one element with its `data-feature`, every state in requirements section 8 is reachable through the review bar or the data, both languages are complete (no key falls back to English). Ask Maurice for the Stage A approval.

## Stage B: rewrite the frontend, extend the backend

### Deviation approved by Maurice (26.10.2026)

The build rules below assume a fresh app with a fixture layer. This repository already has a Laravel 13 backend with Eloquent models, policies, actions, Pest tests and Reverb broadcasting. Therefore: keep the backend and extend it; skip the fixture repositories and service provider bindings of Phase 4; build the new frontend directly against the extended backend. Everything else in the build rules applies, in particular the requirements document rules, the data classes as the single source of truth for Inertia props with generated TypeScript types, feature tests per acceptance criterion with feature IDs, and the three-layer token file.

### Frontend

- Remove PrimeVue, `@primeuix/themes` and `tailwindcss-primeui`. Install shadcn-vue on Tailwind 4 the way the official Laravel Vue starter kit does it for the installed Laravel version; verify against the current Laravel and shadcn-vue docs with Boost before installing anything, and ask before changing dependencies (`CLAUDE.md`).
- Rebuild every page from the prototype: start page, dashboard, group, poll, event, list, create and edit pages (poll, event, list, group), settings, friends, shared list, group settings, join page, notifications panel. Old pages under `resources/js/pages` are replaced, not patched. Keep the Inertia page names stable where controllers already render them, or rename controller and page together.
- Map the prototype components to shadcn-vue primitives (Button, Input, Textarea, Checkbox, RadioGroup, Select, Dialog, Sheet, Popover, Calendar, DatePicker, Badge, Avatar, Toast, Tooltip) and build the project components on top: FormField, ChoiceGroup, DateBlock, Mark, AvatarStack, ProgressBar, EmptyState, FormPage, OptionCalendar, the poll grid, the claim controls. One component set, reused everywhere.
- Tokens: port `docs/prototype/css/tokens.css` and `css/depth.css` into `resources/css/app.css` in the three layers from the build rules, in OKLCH, with the semantic names listed there (plus `--font-tag` and the depth tokens) and dark mode as a class variant. Markup uses semantic utilities only.
- Interface language (F-24): English and German with a per-account setting and browser detection on first visit. Choose the i18n approach after checking the docs (Laravel lang files exposed to Vue, or a maintained Inertia-compatible package); ask if two options are equally viable.
- Keep Laravel Echo and Reverb for live updates (F-21), presence and comments; keep Wayfinder for routes.

### Backend changes the requirements need

Derive the exact list from `docs/requirements.md`; this is the known delta against the current backend:

- Polls: `type` and `mode` names as in the requirements (date finder and attendance; day and slots); fixing selects one or more options (today `chosen_option_id` allows one), each fixed option creates an event; a poll can belong to an event (follow-up, F-12) and its audience is then the accepted participants; closing and reopening keep created events.
- Events (new, F-10): group, organizer, title, description, location, start with optional time, optional end, origin option, parent event (one level). Participation (new, F-11): status accepted, pending, declined; derived from responses when fixing, all pending plus the organizer accepted for direct events, reset to pending when date or time changes, decline releases the person's claims on the event's lists after confirmation.
- Lists (rosters today): can belong to an event; event lists are visible only to participants; claims on event lists only for accepted participants. Standalone and group lists keep their current behavior. Items without quantity are tasks with a single claimant (already so).
- Dashboard (F-22): "Waiting for you" query over open polls without a complete response and events with pending status.
- Notifications (F-23): database notifications for the listed triggers, read state, live delivery through Reverb; no emails except the login link.
- Accounts: language setting (F-24).
- Keep: magic links and shadow accounts, groups with owner, admin and member, friends, custom fields, split claims, claim-for, duplication, share links with in-place login, attendance link with warning and confirmation, comments, slugs in URLs.

Write migrations, models, factories, policies, Form Requests and actions in the existing style (`.ai/rules`). Every acceptance criterion in the requirements gets a feature test that names its feature ID. Existing tests stay unless the requirement behind them changed; then adjust them with the requirement reference.

### Order of work in Stage B

1. Tokens and the component set, verified in the browser against the prototype.
2. Backend delta with tests.
3. Pages in the order of the main flows in requirements section 6: group and polls, fixing and events, participation, lists and claims, dashboard, start page and account pages, notifications.
4. Full suite (`ddev artisan test --compact`, `npm run check`, `npm run types:check`), browser walkthrough of all six main flows in both languages and both themes.
5. Propose the retirement of `BRING_A_THING.md` and `IMPLEMENDATION.md`.

## Claude Code build rules: apps

The concept prototype in `prototype.html` shows the approved UI concept. Use it as a reference for layout, content hierarchy and flows, not as code to copy. Standard features it shows as stubs get built in full.

### Requirements document

- Store the requirements document as `docs/requirements.md`. It defines behavior and nothing else.
- Never edit it without explicit approval. Propose each change with the affected sections before and after, wait for approval, then apply it and raise the version.
- Implementation notes, technical decisions and progress go into code, commit messages or other files, never into `docs/requirements.md`.
- When the implementation needs behavior the document does not define, ask before implementing it.

### Phase 4: fixture app

- Start from the official Laravel Vue starter kit for the installed Laravel version, verified against the current docs. Authentication stays real from the start.
- Write migrations, models, factories, routes, controllers, Form Requests and policies as the real backend needs them, derived from the requirements document.
- Controllers access data only through repository interfaces. Fixture implementations serve in-memory data and keep writes for the session, so every flow works. A service provider binds the fixture implementations. External services follow the same pattern with interface and fixture implementation.
- PHP data classes define every Inertia page prop and are the single source of truth for types. TypeScript types for the frontend are generated from them. Verify that the chosen package supports the installed Laravel version before using it, and ask if no supported option exists.
- Fixtures cover the empty, error, validation, pagination and permission cases from the requirements, not only the happy path.
- Every acceptance criterion gets a feature test that names its feature ID.
- Tailwind tokens live in `resources/css/app.css` in three layers: primitives (brand, neutral and status scales in OKLCH, fonts, radius, motion), semantic tokens with light and dark values (`surface`, `surface-muted`, `surface-raised`, `fg`, `fg-muted`, `fg-inverted`, `border`, `border-strong`, `primary`, `primary-hover`, `primary-fg`, `accent`, `success`, `warning`, `danger`, `focus-ring`), and component tokens only where a component deviates. Markup uses semantic utilities only. Dark mode uses a class variant.

### Phase 5: frontend iteration

Iterate on pages and components against the fixtures. Changes that alter behavior go through the change rule of the requirements document first.

### Phase 6: backend integration

Implement Eloquent repositories and real service clients, switch the bindings, remove the fixture implementations, and run the full test suite. Pages, props and generated types stay unchanged; a required change to them indicates a contract error and needs a review before it gets applied.

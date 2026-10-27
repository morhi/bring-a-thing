# Requirements: Bring a Thing

## 1. Header

- **Project:** Bring a Thing
- **Type:** App
- **Version:** 1.14
- **Approved:** 26.10.2026

## 2. Goal

Bands, clubs, groups of friends and teams organize shared dates with several tools today: Doodle to find a date, a messenger for accepting and declining, and a shared list for who brings or does what. Once a date is fixed, the connection is lost. Follow-up dates such as rehearsals go to everyone instead of only the people who accepted, and tasks end up with people who are not even coming.

Bring a Thing connects these steps in one chain: find a date, fix it, determine the participants, schedule follow-up dates only with them, and then distribute tasks and things to bring. The app stays general and fits a band gig as well as a club event, a trip or a dinner. The goal is reached when an organizer creates a poll for an occasion, fixes the date after the responses and opens a list for the participants in under 5 minutes, without needing another tool.

## 3. Stage and scope

**Stage: MVP.** A backend with login, groups, lists, polls, comments and live updates already exists. The frontend gets a complete redesign. The MVP covers all features in section 5, including the event chain (F-09 to F-12) that the existing backend does not cover yet.

**Later stages:**
- Feedback to the platform team: group owners and admins rate their satisfaction and suggest improvements. A global super user role manages the responses with the statuses "planned" and "rejected".

## 4. Users and roles

- **Guest:** person without login who holds a share link. Sees the shared list without other people's email addresses. Claiming or adding opens a login on the same page (F-19).
- **Account:** any logged-in person, including a shadow account (F-01). Creates groups and standalone lists and manages their own friends.
- **Group owner:** creator of a group. May do everything in the group, including deleting it and granting or revoking the admin role.
- **Group admin:** member appointed by the owner. Invites and removes members (not the owner), edits all polls, events and lists of the group, adds items regardless of F-14 and claims items for others. May not delete the group, change admin roles or switch the group option (F-03).
- **Member:** sees all polls and events of the group and the group lists, responds to polls, accepts or declines events, comments and claims items.
- **Organizer:** whoever creates a poll or an event. Edits, closes and fixes dates like an admin, but only for their own polls and events.
- **List owner:** whoever creates a list. Manages settings, items, custom fields and the share link of that list.
- **Participants:** members with the participation status "accepted" or "pending" for an event. Only they see the lists of that event (F-11, F-13). Only participants with "accepted" see and create its follow-up polls (F-12).

## 5. Features

### MVP

#### F-01 Login via magic link (Core)

**User goal:** Join without a registration barrier, even when only invited.

**Rules:**
- The start page carries a single form for login and registration. The person enters their email and receives a login link by email. If the account has no name yet, the form asks for it. If the account has a password, the person can log in with the password instead.
- An invitation by email (F-04) creates a shadow account immediately. It appears as a member right away, and the invited person logs in through a login link without registering.
- A person who joins through an invite link (F-04) with name only gets a shadow account without an email and stays logged in on that browser. They can add an email at any time and receive a login link to keep access on other devices.
- The login link is signed and expires.
- A password is optional and can be set in the settings (F-02). The login link stays available afterwards.
- A passkey is optional and can be saved in the settings (F-02). The start page offers a "Log in with passkey" button that logs in without entering an email. The login link and the password stay available.
- The login email is the only email the app sends.

**Acceptance criteria:**
- A new email address receives a login link after entering email and name, and the link leads to the dashboard.
- An expired or altered link shows an error and does not log in.
- An email address invited twice results in exactly one account.
- A name-only joiner who adds an email can log in on another device through the login link.
- An account without a password shows no password field.
- A guest can request passkey login options on the start page, and a passkey login without a credential does not log in.
- After a passkey login, the person lands on the dashboard, or back on the shared list when a claim is pending (F-19).

#### F-02 Account and settings (Secondary)

**User goal:** Manage one's own name, password and passkeys.

**Rules:**
- The settings contain name, set or change password, saved passkeys, and logout. Common practice applies.
- Passkeys: the person saves a passkey under a name of their choice, sees each one with its creation and last-use date, and removes it after a confirmation. Saving needs no password, so accounts that only use the login link can add one. A passkey belongs to one account and cannot be removed by another.

**Acceptance criteria:**
- After setting a password, login with email and password succeeds.
- The settings list the saved passkeys of the account.
- An account without a password can start saving a passkey.
- Removing a passkey deletes it and shows a confirmation message.
- A passkey of another account cannot be removed.

#### F-03 Groups (Core)

**User goal:** Create a fixed circle of people once and reuse it for all polls, events and lists.

**Rules:**
- A group has a name and members with the roles owner, admin or member (section 4).
- The owner appoints members as admins and revokes the role again. The owner cannot be removed and can only delete the group.
- Only the owner renames the group.
- The group has the option "Members can set the participation status for others", off by default. Only the owner switches it. Owners and admins can always set the status themselves.
- A new group is created on its own page with name and invitations (F-04). Inviting can be skipped and done later.
- Deleting a group deletes all its polls, events and lists after a confirmation.

**Acceptance criteria:**
- An admin sees no action to delete the group and no action to change roles, and cannot switch the group option.
- After deleting a group, its lists are no longer reachable.

#### F-04 Inviting members (Core)

**User goal:** Bring people into the group by email or by a shared link without them registering first.

**Rules:**
- Owners and admins invite by email. The app creates a shadow account if needed (F-01) and saves the person as a friend of the inviting person (F-05).
- Until their first login, the member carries the label "Invitation pending".
- One invite field takes an email address or a name. It suggests matching friends by name or email, and an email that matches a friend selects that friend. Text that is neither a friend nor an email address offers no invitation. Selected people appear as removable chips. The same component offers "Copy invite link", and it is used when creating a group, on the group page and in group settings. The link's lock setting is managed in group settings.
- Owners and admins remove members. Admins cannot remove the owner.
- Removing a member ends the membership only. Participations, claims and responses stay.
- Owners and admins create one invite link per group and share it through any messenger. The "Copy invite link" button creates it on first use.
- The link has a lock setting. Open (default): a person enters a name and joins as a member at once, labeled "Joined via link". Closed: the person must enter an email and log in through a login link before joining.
- A person who joins through the link has the same access as any other member.
- Owners and admins revoke or regenerate the link in group settings (F-03). The old link then shows "Not found".

**Acceptance criteria:**
- An invited person appears in the member list immediately with "Invitation pending", and the label disappears after their first login.
- Typing the first letters of a saved friend shows them as a suggestion.
- Typing a friend's email address selects that friend and creates no second invitation.
- A member without the admin role sees no invite form.
- With an open link, a person who enters a name appears in the member list at once.
- With a closed link, a person appears as a member only after logging in with an email.
- After revoking, the old link shows "Not found".
- A member without the admin role sees no invite link button.

#### F-05 Friends (Secondary)

**User goal:** Avoid retyping frequent contacts for every invitation.

**Rules:**
- Every invitation saves the invited person as a friend. On the "Friends" page, the person adds friends by email and removes them.
- Removing a friend does not change any group membership.

**Acceptance criteria:**
- After an invitation, the person appears on the "Friends" page.
- A removed friend stays a member of the groups they were in.

#### F-06 Date finder poll (Core)

**User goal:** Find the right date among several proposals.

**Rules:**
- Members create a poll in a group. The respondents are all group members, except for a follow-up poll (F-12).
- The poll works by day or with time slots. Each option consists of a date. With time slots it also has start and end. Every option can carry an optional label (for example "Lunch" or "Room free until 22:00"), and the grid header shows it on one line. Several slots on the same day are possible.
- With two or more slots, "Use this time for all" applies one slot's start and end to all other slots. Locked options (F-08) keep their time.
- Each respondent answers each option with yes, maybe or no and can change their answers while the poll is open.
- Answering works by keyboard: each option is one tab stop, and the arrow keys move between yes, maybe and no and save the selected answer right away.
- The view shows a grid with all responses. The options with the most yes answers carry the label "Best option". On a tie in yes answers, only the options with the fewest no answers keep the label. Options still tied all carry the label.
- The mode (by day or time slots) cannot change after creation.
- A "Table / Calendar" toggle switches the view. The calendar shows a week grid for a time-slot poll and a month grid for a by-day poll. Options can be answered in both views, and the tab order follows the options chronologically.

**Acceptance criteria:**
- A poll with three time slots on the same date shows three separate columns.
- When a person changes their answer from no to yes, the grid shows one yes answer and no additional row.
- Two options with three yes and one no answer each both carry "Best option". Of two options with three yes answers, the one with fewer no answers alone carries it.
- "Use this time for all" with 18:00 to 20:00 on five slots sets all five to these times.
- An option with a label shows it in the grid header.
- In an edited poll, "Use this time for all" leaves options with answers unchanged.
- An answer chosen with the arrow keys is saved right away and shown after a reload.
- The calendar view of a time-slot poll shows a week grid and of a by-day poll a month grid; options can be answered there, and tabbing moves through them in chronological order.

#### F-07 Attendance poll (Secondary)

**User goal:** See over a period who is around on which day or in which slot, for example at a camp or a holiday week.

**Rules:**
- By day, the app creates one option per day from a date range. With time slots, the organizer enters each slot individually as in F-06.
- Responses follow F-06. The poll has no best option and cannot be fixed (F-09). It stays open until the organizer closes it.

**Acceptance criteria:**
- The range 03.08. to 07.08. results in five options.
- An attendance poll offers no "Fix date" action.

#### F-08 Editing and closing polls (Core)

**User goal:** Adjust a poll afterwards and stop responses.

**Rules:**
- Organizer, owner and admins rename the poll, add options, remove options together with their responses and delete the poll.
- An option that has answers keeps its date and time. Removing it, together with its answers, is the only way to change them, and asks for a confirmation.
- They close the poll and reopen it. A closed poll accepts no responses and allows no changes to options.
- A date finder poll closes by fixing dates (F-09). An attendance poll closes without a selection.
- Reopening keeps events that were already created.

**Acceptance criteria:**
- A response to a closed poll is rejected with the message "This poll is closed."
- A member without the admin role sees no edit actions on other people's polls.
- After reopening a fixed poll, its events still exist.
- On an option with answers, the date and time fields are disabled; on an option without answers they can be edited.

#### F-09 Fixing dates (Core)

**User goal:** Turn the responses into one or more binding dates, for example three rehearsals out of ten proposals.

**Rules:**
- Organizer, owner and admins select one or more options of a date finder poll and confirm. The poll closes.
- Each selected option creates an event (F-10) with the poll's title, the option's date and time if any, and a reference to the poll.
- The app derives participation per event from the responses: yes results in "accepted", maybe results in "pending", no or no response results in no participation status.
- For a follow-up poll (F-12), the events get the source event as their parent event.
- A fixed option (one that has an event) cannot be removed, and its date and time cannot be changed on the poll.

**Acceptance criteria:**
- Fixing three options creates three events with the options' dates.
- A person with yes on option A and maybe on option B is "accepted" for event A and "pending" for event B.
- A person without a response appears as a participant in none of the events.

#### F-10 Events (Core)

**User goal:** Track a fixed date with acceptances and declines, also without a prior poll.

**Rules:**
- An event belongs to a group and has a title, description, location, start (date, optional time) and an optional end.
- An event results from fixing a date (F-09) or is created directly: a member creates it and thereby asks all group members to accept or decline. This replaces a poll with only one date.
- For a directly created event, every member starts as "pending". The organizer is "accepted".
- Organizer, owner and admins edit and delete the event. Deleting removes its lists, follow-up polls and child events after a confirmation.
- When the date or time changes, the app resets all acceptances to "pending", because the acceptance applied to the old date.
- The event shows acceptances, pending responses and declines with counts, its lists, follow-up polls and child events.
- An event whose start has passed counts as past. There is no other status.

**Acceptance criteria:**
- A directly created event in a group with five members shows one accepted organizer and four pending responses.
- After moving the event from 12.03. to 19.03., all acceptances are "pending" again.
- Deleting an event with a list also removes the list.

#### F-11 Participation (Core)

**User goal:** Accept or decline bindingly without leaving claimed items hanging.

**Rules:**
- Every group member accepts or declines while the event is not past, also without a previous participation status.
- Organizer, owner and admins remove participants and set the status for others. Members can set the status for others only when the group option allows it (F-03). The per-person status controls appear after the "Manage" action on the participation block, for everyone who may use them.
- A person who declines and has claimed items in the event's lists sees these items in a confirmation. After confirming, the app releases the claims. A status set for someone else, and removing a participant, never releases their claims. Only the person's own decline does.
- Only participants see the event's lists (F-13). Only participants with "accepted" see follow-up polls and the action to create them (F-12). Members without participation or with a decline see only the event's key details.

**Acceptance criteria:**
- A decline with two claimed items shows both items in the confirmation, and both are free again afterwards.
- When an admin sets a member to "declined", the member's claims stay.
- A member who declined does not see the event's lists.
- A past event allows no accepting or declining.

#### F-12 Follow-up poll (Core)

**User goal:** Schedule rehearsals or preparation dates only with the people who take part in the event.

**Rules:**
- Within the event, participants with "accepted" create a date finder or attendance poll. Respondents and visibility are limited to participants with "accepted".
- The circle follows current participation: whoever accepts later sees the poll and can respond. Whoever declines no longer sees it, and their responses no longer count.
- Fixing dates follows F-09, and the new events attach to the source event.
- Child events do not offer follow-up polls. Follow-up polls exist only on events without a parent event.

**Acceptance criteria:**
- A group member without acceptance sees the follow-up poll neither in the group nor on the dashboard.
- A person with "pending" sees neither the follow-up polls nor the "New follow-up poll" action.
- A person who accepts after the follow-up poll was created can respond to it.
- An event fixed from a follow-up poll appears in the source event under the child events.
- A child event shows no "New follow-up poll" action.

#### F-13 Lists (Core)

**User goal:** Collect who brings or takes on what.

**Rules:**
- A list has a title, a description and an optional date. It is standalone, belongs to a group or belongs to an event.
- A standalone list is visible only to its owner, to people with a share link (F-19) and to people with claims. A group list is visible to all members. An event list is visible only to participants (F-11).
- A list has no status of its own. It is past when its date has passed.
- List owner, group owner and group admins edit and delete the list.

**Acceptance criteria:**
- A group member does not see another member's standalone list.
- An event list appears for an accepted person and is missing for a person who declined.

#### F-14 Items (Core)

**User goal:** Describe what needs to be brought or done precisely enough that it is clear what is needed.

**Rules:**
- An item has a name, an optional quantity and unit (for example "2 kg potatoes"), notes, a date and values for custom fields (F-16).
- An item without a quantity is a task and has at most one person claiming it.
- An item's date overrides the list's date. A list can thus contain "Dinner Monday" and "Dinner Tuesday", each with its own date. Items with a date appear grouped by date.
- The list owner can always add items. With the setting "Members can add items", everyone who sees the list and people with a share link can do so as well. Group owner and admins can do so regardless of the setting.
- Only list owner, group owner and admins edit and delete items.

**Acceptance criteria:**
- Without the setting, a member sees no add action; an admin does.
- An item dated 14.03. in a list dated 12.03. appears under 14.03.

#### F-15 Claiming (Core)

**User goal:** Say "I'll bring this" or "I'll do this", also for part of the quantity.

**Rules:**
- For an item with a quantity, several people each claim a partial quantity. The total may not exceed the quantity.
- Every person changes and removes their own claim.
- List owner, group owner and admins claim for another person who can see the list, and remove other people's claims.
- For event lists, only participants with "accepted" can claim. People with "pending" see a note that they need to accept first.
- The item shows who claimed how much and what is still missing.

**Acceptance criteria:**
- For "10 chairs" with claims of 6 and 4, the app rejects a further claim.
- A task with one claiming person offers no claim to others.
- An admin claims an item for a member, and the item shows the member as claiming.
- A person with "pending" sees the note about accepting instead of the claim action on an event list.

#### F-16 Custom fields (Secondary)

**User goal:** Adapt items to the occasion, for example with "Allergens" or "Color".

**Rules:**
- The list owner creates, renames and deletes text fields per list. Every item shows the fields with their values.
- Deleting a field removes its values after a confirmation.

**Acceptance criteria:**
- A field "Allergens" appears in the form of every item of the list and not in other lists.

#### F-17 Duplicating lists (Secondary)

**User goal:** Reuse a proven list as a template, for example last week's meal plan.

**Rules:**
- The list owner duplicates a list. The copy takes over title, description, items and custom fields, but no claims, no comments and no date.

**Acceptance criteria:**
- The copy of a list with three items and two claims contains three items without claims and without a date.

#### F-18 Attendance link (Secondary)

**User goal:** See when claiming whether one is around on the day in question.

**Rules:**
- A group list or an item optionally references an option of an attendance poll of the same group. An item's reference overrides the list's.
- List and item show the day with a link to the poll.
- A person who answered no or nothing for that day sees a warning and must confirm a claim ("Claim anyway"). The app never blocks the claim. This also applies when claiming for others.

**Acceptance criteria:**
- A person with no for the linked day sees a confirmation before claiming; a person with yes does not.
- The selection offers only attendance polls of the same group.

#### F-19 Share link (Secondary)

**User goal:** Let people outside the group take on items without adding them to the group.

**Rules:**
- The list owner enables, regenerates or disables a share link. Regenerating invalidates the old link.
- Anyone with the link sees the list without logging in. Other people's email addresses do not appear.
- Claiming and, with the setting from F-14, adding open the login on the same page. After login, the app completes the action automatically and returns to the list.
- Whoever may see the list anyway lands on the normal list view.

**Acceptance criteria:**
- A guest claims an item, logs in through the login link and then sees their claim in the list.
- After regenerating, the old link shows a note that it is no longer valid.

#### F-20 Comments (Secondary)

**User goal:** Clarify details without switching to another channel.

**Rules:**
- Lists, items and events each have a comment thread. The event's thread is collapsed by default.
- Cmd+Enter or Ctrl+Enter sends a comment.
- Author, group owner and admins delete comments. On lists and items the list owner does too. On events nobody else does, not even the organizer.

**Acceptance criteria:**
- A comment on an item appears only on the item, not in the list's thread.
- A comment on an event appears only on that event.
- Cmd+Enter in the comment field sends the comment.
- A member who is neither author, group owner nor admin sees no delete action on an event comment.

#### F-21 Live updates (Secondary)

**User goal:** See what others are doing without reloading.

**Rules:**
- Claims, items, comments, poll responses and acceptances appear in all open views without reloading.
- Lists and polls show who is currently viewing them.

**Acceptance criteria:**
- When a person claims an item, a second open session shows the claim within 2 seconds.

#### F-22 Dashboard (Core)

**User goal:** See at a glance where one needs to respond or get something done.

**Rules:**
- The dashboard shows "Waiting for you" at the top: open polls without a complete response and events with the status "pending".
- Below, it shows upcoming events with one's own acceptance, one's own claims on upcoming lists, groups and standalone lists.
- Past events and lists are missing from the dashboard and stay reachable through group and event.

**Acceptance criteria:**
- An open poll in which a person answered two of five options appears under "Waiting for you".
- After accepting, the event disappears from "Waiting for you" and appears under the upcoming events.

#### F-23 Notifications (Secondary)

**User goal:** Learn what has happened without checking everything.

**Rules:**
- A bell in the header shows in-app notifications for: invitation to a group, new poll, new event, fixed date, moved event, new comment in a list with one's own claim, claim or release by another person on one's behalf.
- New notifications appear live. The person marks them as read.
- The app sends no emails for these.

**Acceptance criteria:**
- After fixing a date, the bell shows an unread notification to every accepted person.
- "Mark all as read" sets the counter to zero.

#### F-24 Interface language (Secondary)

**User goal:** Use the app in English or German.

**Rules:**
- On a first visit, the app picks the language from the browser. If the browser asks for neither English nor German, it uses English.
- A language switch on the start page and in the settings (F-02) changes the language.
- A logged-in account keeps its choice across devices.
- The login email uses the account's language.
- Content that people write (titles, items, comments) is not translated.

**Acceptance criteria:**
- A browser set to German shows the start page in German on the first visit.
- A browser set to French shows English.
- After switching to German in the settings, a login on another device shows German.
- The login email for an account set to German is in German.

#### F-25 Search and quick actions (Secondary)

**User goal:** Jump to a group, poll, event or list, or start a common action, without clicking through pages.

**Rules:**
- The header carries a search field in the middle; on narrow screens it shows as a search icon. Cmd+K on Mac and Ctrl+K elsewhere opens and closes the palette, Esc closes it.
- Without input, the palette shows the quick actions "New group", "New list", "Dashboard", "Settings" and "Friends". Typing filters them.
- From two letters on, the palette also searches the titles of groups, polls, events and lists, grouped by type with a heading. Each type shows at most five results.
- Results only contain records the person may open: their groups, the polls and events of those groups by the rules of F-06, F-10 and F-12, and the lists by the rules of F-13.
- The arrow keys move through the entries and Enter opens the selected one. While searching, the palette shows a loading state; without results it says so.

**Acceptance criteria:**
- Searching requires a login.
- An empty query or a single letter returns no records, only the quick actions.
- "gig" finds the group, poll, event and list with "gig" in their title, each with its heading and link.
- A poll, event or list of a group the person is not a member of never appears.
- An event list or follow-up poll appears only once the person takes part in the event.
- With seven matching polls, the palette shows five.

## 6. Structure

**Navigation:** header with logo (leads to the dashboard), search and quick actions (F-25), notifications (F-23) and user menu (settings, friends, logout). There is no permanent sidebar; groups, events and lists link to each other on their pages.

**Pages:**
- **Start page:** public page with explanation, login form (F-01) and language switch (F-24).
- **Dashboard:** F-22, actions "New group" and "New list".
- **New group:** name and invitations (F-03, F-04).
- **Group:** members with invite (F-04), polls, upcoming and past events, group lists, actions "New poll", "New event" and "New list".
- **Group settings:** name, invite link, option, roles, delete (F-03, F-04).
- **Poll:** response grid, best option, fix dates (F-06, F-07, F-09).
- **Poll edit:** the "New poll" page in edit mode with the same components: title, options, close, delete (F-08).
- **Event:** key details, participation with one's own accept or decline, lists, follow-up polls, child events, edit (F-10 to F-12).
- **List:** items with claims, comments, attendance day (F-13 to F-15, F-18, F-20).
- **List edit:** the "New list" page in edit mode with the same components: title, description, date, add setting, custom fields, attendance link, share link, duplicate, delete (F-14, F-16 to F-19).
- **Shared list:** view for guests with login on the same page (F-19).
- **New poll:** title, type, granularity, option calendar and option rows, audience note; from a group or, as a follow-up, from an event (F-06, F-07, F-12).
- **New event:** title, date, time, location, description, audience note (F-10).
- **New list:** title, description, date, placement, add setting (F-13, F-14).
- **Settings** and **Friends:** F-02, F-05, language switch (F-24).

Editing items happens in a dialog. Creating a group, a poll, an event or a list has its own page, reached from the group, the event or the dashboard, with a cancel action that returns to where the person came from.

**Main flows:**
1. **Find a gig date:** open the group, "New poll", create a date finder with four dates. Members respond. The organizer chooses "Fix date", selects one date and confirms. The event is created with the acceptances.
2. **Rehearsals only with those who accepted:** open the event, "Follow-up poll", date finder with time slots on several evenings. Only accepted participants respond. The organizer fixes three slots; three rehearsals appear under the event.
3. **Distribute tasks and things to bring:** open the event, "New list", enter items such as "PA system", "Extension reel" or "Drinks, 2 crates". Participants claim; the organizer assigns the remaining items.
4. **Single date:** open the group, "New event" with date and time. Members accept or decline on the dashboard or in the event.
5. **Decline after accepting:** open the event, "Decline", confirmation with one's own claims, confirm. The items are free again.
6. **Taking part from outside:** open the list, enable the share link and share it. A guest claims an item and logs in on the same page.

## 7. Data

- **Account:** email (text, required, unique), name (text, required from the first login), password (optional), language (choice: English, German, required).
- **Friend:** account (required), befriended account (required), display name (text).
- **Group:** name (text, required), owner (account, required).
- **Membership:** group, account, role (choice: owner, admin, member, required), accepted at (date, empty until the first login).
- **Poll:** group (required), organizer (account, required), title (text, required), type (choice: date finder, attendance, required), mode (choice: by day, time slots, required), closed at (date), event (optional, for follow-up polls).
- **Poll option:** poll (required), date (required), start and end (time, required for time slots), label (text), fixed (yes/no).
- **Response:** option, account, answer (choice: yes, maybe, no, required). At most one response per account and option.
- **Event:** group (required), organizer (account, required), title (text, required), description (text), location (text), start (date, required; time optional), end (date and time, optional), origin (poll option, optional), parent event (optional, one level).
- **Participation:** event, account, status (choice: accepted, pending, declined, required). At most one participation per account and event.
- **List:** title (text, required), description (text), owner (account, required), group or event (each optional, at most one), date (date), members can add items (yes/no), share link (text, optional), attendance day (option of an attendance poll, optional).
- **Item:** list (required), name (text, required), quantity (number, optional), unit (text), notes (text), date (date), attendance day (optional), custom field values.
- **Claim:** item, account, quantity (number, required for items with a quantity). At most one claim per account and item.
- **Custom field:** list (required), name (text, required).
- **Comment:** list, item or event (exactly one, required), author (account, required), text (required), timestamp.
- **Notification:** account, type (choice per F-23), reference, read at (date).

Groups, lists, items, polls, events and custom fields appear in URLs with random identifiers instead of sequential numbers, so nobody finds other people's content by trying numbers.

## 8. States and edge cases

- **No groups and lists:** the dashboard explains both entry points and offers "New group" and "New list".
- **Nothing waiting:** "Waiting for you" shows "All caught up." instead of an empty area.
- **Group without other members:** the group shows the invite form prominently.
- **Empty list:** shows the action "Add first item" or, without the right to add, a note that the owner has not added anything yet.
- **Everything claimed:** the list shows "All taken".
- **Poll without responses:** fixing is possible; the events then have only the organizer as participant if they answered yes.
- **Poll closed:** the grid is read-only and shows the fixed options highlighted.
- **Fixing without a selection:** the confirmation stays disabled.
- **Event without acceptances:** lists and follow-up polls can be created; the page shows a note that nobody has accepted yet.
- **Event past:** accepting and declining are disabled, lists stay readable.
- **Child event:** shows no "New follow-up poll" action; lists can still be created.
- **Group settings without admin role:** a member sees "Not found".
- **Event moved:** acceptances switch to "pending"; claims stay.
- **Decline without claims:** no confirmation needed.
- **Status set for another person:** claims stay, also on "declined".
- **Claim above the remaining quantity:** rejected with the remaining quantity in the message.
- **No access:** a list, poll or event without permission shows "Not found", so its existence stays hidden.
- **Invalid share link:** shows a note that the link is no longer valid.
- **Expired login link:** shows a note with the action "Request new link".
- **Attendance link without an attendance poll in the group:** the field does not appear.
- **Live update connection lost:** the app shows a note and refreshes after reconnecting.

## 9. Non-functional requirements

- **Language:** English and German (F-24). Dates follow the active language: 15.10.2026 in German, 15 Oct 2026 in English. Times in 24-hour format in both.
- **Devices:** mobile first from 375 px width for all functions, desktop up to 1440 px without functional differences. No horizontal scrolling except in the poll's response grid.
- **Accessibility:** WCAG 2.2 level AA, including the response grid.
- **Legal:** Swiss Federal Act on Data Protection (revFADP) and GDPR, privacy policy and legal notice on the start page.
- **Volume:** groups up to 50 members, polls up to 60 options, lists up to 200 items. Expected usage: about 5 groups per account and about 5 polls, events and lists each per group per year. Pages load in under 1 second, live updates appear in under 2 seconds.

## 10. Out of scope

- Calendar synchronization and calendar export
- Chat beyond comments
- Costs, fees and splitting expenses
- Setlists and other occasion-specific content
- Native apps
- Polls without a group and poll responses without an account
- Recurring events and automatically recurring lists (duplication only)
- Emails other than the login email, including reminders
- Status fields for lists and items; past is derived from the date
- More than one level of child events
- **Later:** QR codes for share and invite links, automatic deletion of old polls and lists, list expiry after the event, Excel export of poll results, event participation and list items.

## 11. Open questions

No open questions.

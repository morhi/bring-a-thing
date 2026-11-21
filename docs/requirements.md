# Requirements: Bring a Thing

## 1. Header

- **Project:** Bring a Thing
- **Type:** App
- **Version:** 1.35
- **Approved:** 21.11.2026

## 2. Goal

Bands, clubs, groups of friends and teams organize shared dates with several tools today: Doodle to find a date, a messenger for accepting and declining, and a shared list for who brings or does what. Once a date is fixed, the connection is lost. Follow-up dates such as rehearsals go to everyone instead of only the people who accepted, and tasks end up with people who are not even coming.

Bring a Thing connects these steps in one chain: find a date, fix it, determine the participants, schedule follow-up dates only with them, and then distribute tasks and things to bring. The app stays general and fits a band gig as well as a club event, a trip or a dinner. The goal is reached when an organizer creates a poll for an occasion, fixes the date after the responses and opens a list for the participants in under 5 minutes, without needing another tool.

## 3. Stage and scope

**Stage: MVP.** A backend with login, groups, lists, polls, comments and live updates already exists. The frontend gets a complete redesign. The MVP covers all features in section 5, including the event chain (F-09 to F-12) that the existing backend does not cover yet.

**Later stages:**
- Feedback to the platform team: group owners and admins rate their satisfaction and suggest improvements. A global super user role manages the responses with the statuses "planned" and "rejected".

## 4. Users and roles

- **Guest:** person without login who holds a share link. Sees the shared list without other people's email addresses. Claiming or adding opens a login on the same page (F-19).
- **Account:** any logged-in person, including a shadow account (F-01). Creates groups, standalone polls, standalone events and standalone lists (F-29) and manages their own friends.
- **Group owner:** creator of a group. May do everything in the group, including deleting it and granting or revoking the admin role.
- **Group admin:** member appointed by the owner. Invites and removes members (not the owner), edits all polls, events and lists of the group, adds items regardless of F-14 and claims items for others. May not delete the group, change admin roles or switch the group option (F-03).
- **Member:** sees all polls and events of the group and the group lists, responds to polls, accepts or declines events, comments and claims items.
- **Organizer:** whoever creates a poll or an event. Edits, closes and fixes dates like an admin, but only for their own polls and events. For a standalone poll or event (F-29) the organizer is the only person who manages it.
- **List owner:** whoever creates a list. Manages settings, items, custom fields and the share link of that list.
- **Link guest:** account that joined through an invite link of an event or poll, or logged in through the share link of an event list, without group membership (F-19, F-26, F-27). Has the rights of a member for the topmost parent of the shared item, everything below it by the existing rules and the shared item itself, and never manages them. Sees the item's group only as a restricted guest group page (F-22, F-26, F-27), never its members or other items. A standalone item has no group to show.
- **Participants:** members and link guests with the participation status "accepted" or "pending" for an event. Only they see the lists of that event (F-11, F-13). Only participants with "accepted" see and create its follow-up polls (F-12).

## 5. Features

### MVP

#### F-01 Login via magic link (Core)

**User goal:** Join without a registration barrier, even when only invited.

**Rules:**
- The start page carries a single form for login and registration. The person enters their email and receives a login link by email. If the account has no name yet, the form asks for it. If the account has a password, the person can log in with the password instead.
- An invitation by email (F-04) creates a shadow account immediately. It appears as a member right away, and the invited person logs in through a login link without registering.
- A person who joins through a group invite link (F-04) logs in like on the start page. With a login link the join completes when they use it, and with a password or passkey it completes at once. The group link creates no account without an email, so nobody loses access after logging out. Event and poll invite links also allow a name-only join (F-26, F-27).
- The login link is signed and expires.
- A password is optional and can be set in the settings (F-02). The login link stays available afterwards.
- A passkey is optional and can be saved in the settings (F-02). The start page offers a "Log in with passkey" button that logs in without entering an email. The login link and the password stay available.
- Every login is remembered on the device: login link, password, passkey and the name-only join of an invite link, also for accounts without a password. A session that ended after idle time is restored silently from the remember cookie. The password login always remembers and has no checkbox.
- Setting or changing the password keeps the current device logged in (F-02).
- A request with an expired page token after a long idle time shows a notice and returns to the page instead of an error page.
- The app sends only three kinds of emails: the login link, the link that confirms an email address and the summary email for fixed dates (F-30).

**Acceptance criteria:**
- A new email address receives a login link after entering email and name, and the link leads to the dashboard.
- An expired or altered link shows an error and does not log in.
- An email address invited twice results in exactly one account.
- A guest who opens a group invite link and enters no email gets a validation error and no account.
- An account without a password shows no password field.
- A guest can request passkey login options on the start page, and a passkey login without a credential does not log in.
- After a passkey login, the person lands on the dashboard, or back on the shared list when a claim is pending (F-19).
- A person who logged in through a login link and has no password is logged in again from the remember cookie after the session is gone.
- A person who logged in with email and password and entered nothing else is logged in again the same way.
- An account without a password cannot log in with a password, and the app shows the normal login failure.
- An Inertia request with an expired page token goes back to the page with a notice.

#### F-02 Account and settings (Secondary)

**User goal:** Manage one's own name, password, passkeys and time zone.

**Rules:**
- The settings contain name, set or change password, saved passkeys, and logout. Common practice applies.
- Passkeys: the person saves a passkey under a name of their choice, sees each one with its creation and last-use date, and removes it after a confirmation. Saving needs no password, so accounts that only use the login link can add one. A passkey belongs to one account and cannot be removed by another.
- Hint: an account without a password and without a passkey sees a dismissible hint beneath the header on every app page except the settings. It recommends setting a password or saving a passkey. For an account without an email it recommends a passkey or adding an email. Dismissing hides the hint for good on all devices of that account.
- Passkey gate: saving or removing a passkey needs a login younger than 15 minutes for every account, except one without password, passkey and email, which saves its first passkey at any time. A login through login link, password, passkey or the name-only join of an invite link starts the 15 minutes, and a session restored from the remember cookie does not.
- Setting or changing the password keeps the current device logged in and does not start the 15 minutes.
- Time zone: the settings contain the person's time zone as a searchable list of IANA zones, for example Europe/Zurich. An account without a stored time zone takes the browser's time zone on its next app visit, and Europe/Zurich when the browser reports none or an unknown one.

**Acceptance criteria:**
- After setting a password, login with email and password succeeds.
- After setting a password, the current device stays logged in and the 15 minutes do not start.
- An account without a password and without a passkey sees the hint with the password or passkey recommendation; an account without an email sees the variant with passkey or email.
- An account with a password, an account with a passkey and a guest see no hint.
- Dismissing the hint stores the dismissal on the account, and the hint stays hidden afterwards.
- Saving a passkey works right after a login through login link, password, passkey and the name-only join.
- On a session restored from the remember cookie, an account without password, passkey and email saves its first passkey.
- On a session restored from the remember cookie, an account with a password, a passkey or an email is refused and sees the next step.
- A login older than 15 minutes blocks saving and removing a passkey, except for an account without password, passkey and email, and removing shows the message.
- The settings list the saved passkeys of the account.
- An account without a password can start saving a passkey.
- Removing a passkey deletes it and shows a confirmation message.
- A passkey of another account cannot be removed.
- An account without a time zone whose browser reports America/New_York stores America/New_York. Without a valid browser time zone it stores Europe/Zurich.
- After a change to Europe/London, an event entered at 19:00 in Europe/Zurich shows 18:00.

#### F-03 Groups (Core)

**User goal:** Create a fixed circle of people once and reuse it for all polls, events and lists.

**Rules:**
- A group has a name and members with the roles owner, admin or member (section 4).
- The owner appoints members as admins and revokes the role again. The owner cannot be removed and can only delete the group.
- Only the owner renames the group.
- The group has the option "Members can set the participation status for others", off by default. Only the owner switches it. Owners and admins can always set the status themselves.
- A new group is created on its own page with name and invitations (F-04). Inviting can be skipped and done later.
- Deleting a group deletes all its polls, events and lists after a confirmation. The delete action sits only in the group settings, never in the group page header.
- The group page shows its polls, then its events, then its lists (F-22).
- Members and admins leave a group with "Leave group" in the group page header, after a confirmation. The owner cannot leave. Leaving ends the membership only; participations, claims and responses stay.
- A guest of the group (F-26, F-27) asks for regular membership with "Request membership" in the group page header. The button then shows "Request sent" and cannot be used again. The owner gets a bell notification (F-23) and accepts or declines the request in the group settings. Accepting makes the guest a member; declining removes the request and the guest stays a guest. Only the owner resolves requests.

**Acceptance criteria:**
- An admin sees no action to delete the group and no action to change roles, and cannot switch the group option.
- After deleting a group, its lists are no longer reachable.
- The group page header shows no delete action; the owner finds it in the group settings.
- A member or admin who leaves a group lands on the dashboard with a confirmation message and no longer reaches the group.
- The owner and a link guest see no "Leave group" action, and leaving is refused for them.
- A guest who requests membership sees "Request sent"; a second request is refused and stores nothing. A member sees no request action.
- The owner sees the pending requests in the group settings and accepts or declines each; an admin cannot resolve them. An accepted guest is a member and the request is gone.

#### F-04 Inviting members (Core)

**User goal:** Bring people into the group by email or by a shared link without them registering first.

**Rules:**
- Owners and admins invite by email. The app creates a shadow account if needed (F-01) and saves the person as a friend of the inviting person (F-05).
- Until their first login, the member carries the label "Invitation pending".
- One invite field takes an email address or a name. It suggests matching friends by name or email, and an email that matches a friend selects that friend. Text that is neither a friend nor an email address offers no invitation. Selected people appear as removable chips. The same component is used when creating a group, on the group page and in group settings.
- Owners and admins remove members. Admins cannot remove the owner.
- Removing a member ends the membership only. Participations, claims and responses stay.
- The group page header carries a "Share" button that opens a dialog. The dialog shows the invite link with "Copy". Owners and admins create one invite link per group in this dialog, copy it and share it through any messenger, and they revoke or regenerate it there. The page has no separate invite link section.
- Members without the admin role see no "Share" button, because only owners and admins manage and copy the group invite link.
- The join page shows the regular login (F-01): email with login link, password or passkey. A guest logs in before joining; the link has no name-only join. After a password or passkey login, an account with a verified email joins at once and lands on the group; otherwise the join page asks for the missing email step. A logged-in account with a verified email joins with one click. The member is labeled "Joined via link".
- A person who joins through the link has the same access as any other member.
- After revoking or regenerating, the old link shows "Not found".

**Acceptance criteria:**
- An invited person appears in the member list immediately with "Invitation pending", and the label disappears after their first login.
- Typing the first letters of a saved friend shows them as a suggestion.
- Typing a friend's email address selects that friend and creates no second invitation.
- A member without the admin role sees no invite form.
- A guest appears as a member only after logging in with an email.
- A guest who enters no email on the join page gets a validation error and is not added.
- After revoking, the old link shows "Not found".
- A member without the admin role sees no "Share" button.
- The owner opens the "Share" dialog in the group page header, creates the link and copies it from the dialog.
- An account with a password or passkey logs in on the join page and lands on the group as a member.

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
- Members create a poll in a group. The respondents are all group members, except for a follow-up poll (F-12). Any account also creates a standalone poll without a group; its respondents are the organizer and the people who joined through its invite link (F-27, F-29).
- The poll works by day or with time slots. Each option consists of a date. With time slots it also has start and end. Every option can carry an optional label (for example "Lunch" or "Room free until 22:00"), and the grid header shows it on one line. Several slots on the same day are possible.
- With two or more slots, "Use this time for all" applies one slot's start and end to all other slots. Locked options (F-08) keep their time.
- Each respondent answers each option with yes, maybe or no and can change their answers while the poll is open.
- Answering works by keyboard: each option is one tab stop, and the arrow keys move between yes, maybe and no and save the selected answer right away.
- "Fill open" next to the view toggle sets yes, maybe or no on every option the person has not answered yet, in one step. Existing answers stay, so the person marks the exceptions first and fills the rest afterwards. The control is disabled once every option has an answer. It works in the table and the calendar view and is refused on a closed poll and for people outside the audience, like a single answer.
- The view shows a grid with all responses. In the table view, the options with the most yes answers carry the label "Best option". On a tie in yes answers, only the options with the fewest no answers keep the label. Options still tied all carry the label. The calendar view shows each option's yes count and no "Best option" label.
- The table uses compact columns with a month header row above the day columns. The person's own cell is one button: a click cycles no answer, yes, maybe, no; the arrow-key rule above stays. Other people's answers show as plain icons. The name column and the total row stay sticky. The best options carry a tinted column.
- Below 640px width, or when the calendar cells are too narrow for the selector, the calendar shows per day the day number, the own answer as a dot and the yes count. Tapping a day opens a panel below the grid with the yes/maybe/no selector, the totals and the note button.
- Each respondent adds one note of at most 200 characters to their own answer on an option, through a dialog. A note needs an answer. Other people see a note marker, and the text shows on hover or focus. Notes work in the table, the calendar and the day panel. Adding a note is refused on a closed poll and for people outside the audience, like an answer.
- Organizer, owner and admins switch the table to "Edit answers". It shows every audience member as a row, including people without any answer, and lets them set yes, maybe or no per person and option. It is refused on a closed poll and for everyone else.
- Below the grid, "No answer yet" lists the audience members without any answer.
- The mode (by day or time slots) cannot change after creation.
- A poll can also be created from outside a group ("New poll" at /polls/new): the placement picker offers "Standalone" and the user's own groups, and the poll is created in the chosen group or standalone. A group the person is not a member of is refused.
- A "Table / Calendar" toggle switches the view. The calendar shows a week grid for a time-slot poll and a month grid for a by-day poll. Options can be answered in both views, and the tab order follows the options chronologically.

**Acceptance criteria:**
- A poll with three time slots on the same date shows three separate columns.
- When a person changes their answer from no to yes, the grid shows one yes answer and no additional row.
- Two options with three yes and one no answer each both carry "Best option". Of two options with three yes answers, the one with fewer no answers alone carries it.
- The calendar view shows the yes count on each option and no "Best option" label.
- In the table, a click on the own cell cycles from no answer to yes, maybe and no; other people's cells show icons only and the best options carry a tinted column.
- At 500px width, the calendar shows per day the day number, the own answer as a dot and the yes count; tapping a day opens the panel with the selector, the totals and the note button.
- A respondent saves a note of 200 characters on a yes answer; another member sees the marker and the text on hover. A note of 201 characters and a note on an option without an answer are refused, and so is a note on a closed poll.
- An admin switches to "Edit answers", sees a member without any answer as a row and sets maybe for them; a member without the admin role sees no "Edit answers" and the action is refused for them and on a closed poll.
- A member without any answer appears under "No answer yet" below the grid and disappears from it after answering.
- "Use this time for all" with 18:00 to 20:00 on five slots sets all five to these times.
- An option with a label shows it in the grid header.
- On the page opened outside a group, a member of two groups picks one and the poll is created in that group; only the member's own groups are offered.
- A poll created as "Standalone" has no group; picking a group the person is not a member of is refused and creates nothing.
- In an edited poll, "Use this time for all" leaves options with answers unchanged.
- An answer chosen with the arrow keys is saved right away and shown after a reload.
- The calendar view of a time-slot poll shows a week grid and of a by-day poll a month grid; options can be answered there, and tabbing moves through them in chronological order.
- A member who answered no on one option and has no answer on two others uses "Fill open" with maybe; the first option still shows no, the two others show maybe, and the answers of other people stay unchanged. On a closed poll the action is refused.

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
- Organizer, owner and admins rename the poll, add options, remove options together with their responses and delete the poll. The delete action sits only on the poll edit page, never in the poll header.
- An option that has answers keeps its date and time. Removing it, together with its answers, is the only way to change them, and asks for a confirmation.
- They close the poll and reopen it. A closed poll accepts no responses and allows no changes to options.
- The poll editor has an optional answer deadline (date and time). The app stores it in UTC and shows it in the viewer's time zone in the poll header, on the dashboard poll rows (F-22) and on "All polls" (F-31). After the deadline the header shows "Deadline passed" and answers stay possible.
- The option "Close automatically at the deadline" closes the poll when the deadline passes. On a date finder, fixing dates stays possible afterwards. Reopening a poll whose deadline has passed turns auto-close off.
- A date finder poll closes by fixing dates (F-09). An attendance poll closes without a selection.
- Reopening keeps events that were already created.
- A person in the poll's audience who was invited by accident withdraws with "Withdraw" in the poll header, after a confirmation. Their responses are deleted, they leave the audience, and the poll no longer shows to them: not on the dashboard, not on the group page, and opening it shows "Not found". Organizer, owner and admins cannot withdraw, and nobody can from a closed poll or a poll with a fixed option. Joining the poll again through its invite link (F-27) lifts the withdrawal.

**Acceptance criteria:**
- A response to a closed poll is rejected with the message "This poll is closed."
- A member without the admin role sees no edit actions on other people's polls.
- The poll header shows no delete action; organizer, owner and admins find it on the poll edit page.
- After reopening a fixed poll, its events still exist.
- On an option with answers, the date and time fields are disabled; on an option without answers they can be edited.
- A member who withdraws from an open poll lands on the dashboard with a confirmation message; their responses are gone, the totals and best option no longer count them, and the poll is missing from their dashboard.
- Organizer, owner, admins, a person outside the audience and everyone on a closed poll see no "Withdraw" action, and withdrawing is refused for them.
- A poll with the deadline 20.12.2026 18:00 in Berlin shows 18:00 to a viewer in Berlin and 12:00 to a viewer in New York; after the deadline the header shows "Deadline passed" and answering still works.
- A poll with auto-close closes when its deadline passes and then rejects responses; a date finder still allows fixing dates.
- Reopening a poll with a passed deadline and auto-close leaves it open and turns auto-close off.

#### F-09 Fixing dates (Core)

**User goal:** Turn the responses into one or more binding dates, for example three rehearsals out of ten proposals.

**Rules:**
- Organizer, owner and admins select one or more options of a date finder poll and confirm. The poll closes.
- Each selected option creates an event (F-10) with the poll's title, the option's date as start date and as end date, the option's time if any, and a reference to the poll.
- The app derives participation per event from the responses: yes results in "accepted", maybe results in "pending", no or no response results in no participation status.
- For a follow-up poll (F-12), the events get the source event as their parent event.
- A fixed option (one that has an event) cannot be removed, and its date and time cannot be changed on the poll.

**Acceptance criteria:**
- Fixing three options creates three events with the options' dates.
- An event fixed from an option dated 12.03. has 12.03. as start date and as end date.
- A person with yes on option A and maybe on option B is "accepted" for event A and "pending" for event B.
- A person without a response appears as a participant in none of the events.

#### F-10 Events (Core)

**User goal:** Track a fixed date with acceptances and declines, also without a prior poll.

**Rules:**
- An event belongs to a group, or is standalone without one (F-29), and has a title, description, location, start (date, optional time) and end (end date, optional end time). The end date is required and defaults to the start date, so a time range on one day needs only the times. An event fixed from a poll (F-09) gets the option's date as end date.
- An event results from fixing a date (F-09) or is created directly: a member creates it and thereby asks all group members to accept or decline. This replaces a poll with only one date.
- For a directly created event, every member starts as "pending". The organizer is "accepted". A standalone event starts with the organizer alone as "accepted"; others join through its invite link (F-26). Only its organizer manages it, and nobody else sets statuses for others.
- Organizer, owner and admins edit and delete the event. The delete action sits only on the event edit page, never in the event header. Deleting removes its lists, follow-up polls and child events after a confirmation.
- Only a real change of the date or the start time can reset acceptances. Saving with an unchanged date and start time, for example after changing only the description, keeps all answers.
- When the date or start time changes, the edit form asks before saving whether to reset all acceptances to "pending", because the acceptance applied to the old date, or to keep them.
- The event shows acceptances, pending responses and declines with counts, then its follow-up polls, its child events and its lists, in this order (F-22).
- Organizer, owner and admins of an event add a sub-event directly under it with "Add sub-event", without a follow-up poll. The sub-event is an event of the same group, or standalone under a standalone event, with the source event as parent. Its participants start as the source event's participants ("accepted" and "pending") with "pending", and the organizer is "accepted". A child event has no sub-events of its own.
- On the group page, a child event shows the hint "Part of <parent event>" next to its title, and a top-level event shows none. On the restricted group page (F-26) the hint appears only when the parent event is listed there too, so the page never names a parent the guest cannot open.
- An event whose start has passed counts as past. There is no other status.
- An event can also be created from outside a group ("New event" at /events/new): the placement picker offers "Standalone" and the user's own groups, and the event is created in the chosen group or standalone. A group the person is not a member of is refused.

**Acceptance criteria:**
- A directly created event in a group with five members shows one accepted organizer and four pending responses.
- After moving the event from 12.03. to 19.03. and choosing "reset", all acceptances are "pending" again.
- After moving the event from 12.03. to 19.03. and choosing "keep", all acceptances stay as they were.
- After changing only the description, all acceptances stay and the form asks nothing.
- An event saved with a start date and no end date gets the start date as end date; an event with start 18:00 and end 20:00 on one day needs no end date input.
- The event header shows no delete action; organizer, owner and admins find it on the event edit page.
- The event page shows follow-up polls before child events and child events before lists.
- Deleting an event with a list also removes the list.
- On the page opened outside a group, a member of two groups picks one and the event is created in that group; only the member's own groups are offered.
- An event created as "Standalone" has no group; picking a group the person is not a member of is refused and creates nothing.
- A sub-event of a standalone event is created only by its organizer and only under a standalone parent.
- A manager who adds a sub-event to an event with three participants gets a child event with those three participants. A plain member and a child event do not offer "Add sub-event".
- The group page names the parent event on a child event and on no top-level event; the restricted group page carries no parent title the guest may not open.

#### F-11 Participation (Core)

**User goal:** Accept or decline bindingly without leaving claimed items hanging.

**Rules:**
- Every group member and every link guest of the event (F-26, F-27) accepts or declines while the event is not past, also without a previous participation status.
- Organizer, owner and admins remove participants and set the status for others. Members can set the status for others only when the group option allows it (F-03). The per-person status controls appear after the "Manage" action on the participation block, for everyone who may use them.
- While a person has no participation status or "pending", the participation block shows "Accept" and "Decline" buttons. Once they have "accepted" or "declined", the block header shows the own status as a chip ("You're in" / "You declined") with a menu that offers the other status, and the buttons disappear.
- Every switch of the own status through the menu asks for a confirmation. A decline lists the person's claimed items in that confirmation and releases them after confirming. The first answer from no status or "pending" needs no confirmation.
- A status set for someone else, and removing a participant, never releases their claims. Only the person's own decline does.
- Only participants see the event's lists (F-13). Only participants with "accepted" see follow-up polls and the action to create them (F-12). Members without participation or with a decline see only the event's key details.
- A person with a participation status who was invited by accident withdraws with "Withdraw" in the event header, after a confirmation that lists their claimed items. Their participation is removed and their claims on the event's lists are released, so the event leaves their dashboard. Group members still reach the event page and see it like a member without participation. Organizer, owner and admins cannot withdraw, and nobody can from a past event.

**Acceptance criteria:**
- A decline with two claimed items shows both items in the confirmation, and both are free again afterwards.
- An accepted person sees the chip "You're in" and no "Accept" or "Decline" buttons.
- Switching from "accepted" to "declined" and back to "accepted" each needs a confirmation.
- A person without a participation status sees both buttons.
- When an admin sets a member to "declined", the member's claims stay.
- A member who declined does not see the event's lists.
- A past event allows no accepting or declining.
- A participant who withdraws from an upcoming event lands on the dashboard with a confirmation message; their participation is gone, their claims are free again, and the event is missing from their dashboard.
- Organizer, owner, admins and everyone on a past event see no "Withdraw" action, and withdrawing is refused for them.

#### F-12 Follow-up poll (Core)

**User goal:** Schedule rehearsals or preparation dates only with the people who take part in the event.

**Rules:**
- Within the event, participants with "accepted" create a date finder or attendance poll. Respondents and visibility are limited to participants with "accepted", with one exception: a link guest who joined through the follow-up poll's own invite link (F-27) sees and answers that poll at once, even without accepting the source event.
- The circle follows current participation: whoever accepts later sees the poll and can respond. Whoever declines no longer sees it, and their responses no longer count.
- Fixing dates follows F-09, and the new events attach to the source event.
- Child events do not offer follow-up polls. Follow-up polls exist only on events without a parent event.
- Child events also arise from "Add sub-event" (F-10). Both kinds sit in the source event under the child events, one level deep.
- Joining through the link of a follow-up poll or a child event also grants access to the topmost parent of the source event (F-26, F-27).
- Follow-up polls, sub-events and events fixed from a poll take the group of their source; under a standalone poll or event they are standalone too (F-29).

**Acceptance criteria:**
- A group member without acceptance sees the follow-up poll neither in the group nor on the dashboard.
- A person with "pending" sees neither the follow-up polls nor the "New follow-up poll" action.
- A person who accepts after the follow-up poll was created can respond to it.
- A link guest of a follow-up poll answers it without having accepted the source event.
- An event fixed from a follow-up poll appears in the source event under the child events.
- A child event shows no "New follow-up poll" action.
- A follow-up poll and a sub-event of a standalone event, and an event fixed from a standalone poll, are standalone.

#### F-13 Lists (Core)

**User goal:** Collect who brings or takes on what.

**Rules:**
- A list has a title, a description and an optional date. It is standalone, belongs to a group or belongs to an event.
- A standalone list is visible only to its owner, to people with a share link (F-19) and to people with claims. A group list is visible to all members. An event list is visible only to participants (F-11).
- A list has no status of its own. It is past when its date has passed.
- List owner, group owner and group admins edit and delete the list. The delete action sits only on the list edit page, never in the list header.

**Acceptance criteria:**
- A group member does not see another member's standalone list.
- An event list appears for an accepted person and is missing for a person who declined.
- The list header shows no delete action; list owner, group owner and admins find it on the list edit page.

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
- The list page header carries a "Share" button that opens a dialog. The dialog shows the share link with "Copy". The list owner enables, regenerates or disables the share link there, and regenerating invalidates the old link. The page has no separate share link section. Other people see the dialog only where they may copy an existing link, and today that is the list owner alone.
- Anyone with the link sees the list without logging in. Other people's email addresses do not appear.
- Claiming and, with the setting from F-14, adding open the login on the same page. After login, the app completes the action automatically and returns to the list.
- Whoever may see the list anyway lands on the normal list view.
- When a guest logs in through the share link to claim or add, the guest also gets access to the topmost parent of the list: the event the list belongs to, or the poll that event was fixed from, by the rule of F-26. A list without an event grants nothing beyond the list. Viewing the list without a login grants nothing.

**Acceptance criteria:**
- A guest claims an item, logs in through the login link and then sees their claim in the list.
- After regenerating, the old link shows a note that it is no longer valid.
- A guest of an event list claims an item through the share link and logs in; the guest then opens the event.
- A guest of a list without an event claims an item through the share link and logs in; the guest gets no other access.
- The list owner opens the "Share" dialog in the list header and copies the link there.

#### F-20 Comments (Secondary)

**User goal:** Clarify details without switching to another channel.

**Rules:**
- Lists, items, events and polls each have a comment thread. The event's thread is collapsed by default.
- Cmd+Enter or Ctrl+Enter sends a comment.
- Author, group owner and admins delete comments. On lists and items the list owner does too. On events and polls nobody else does, not even the organizer.

**Acceptance criteria:**
- A comment on an item appears only on the item, not in the list's thread.
- A comment on an event appears only on that event.
- A comment on a poll appears only on that poll.
- Cmd+Enter in the comment field sends the comment.
- A member who is neither author, group owner nor admin sees no delete action on an event comment.
- On a standalone event only the author deletes a comment, not the organizer.
- A member who is neither author, group owner nor admin sees no delete action on a poll comment; on a standalone poll only the author deletes a comment, not the organizer.

#### F-21 Live updates (Secondary)

**User goal:** See what others are doing without reloading.

**Rules:**
- Claims, items, comments, poll responses and acceptances appear in all open views without reloading.
- Lists and polls show who is currently viewing them.

**Acceptance criteria:**
- When a person claims an item, a second open session shows the claim within 2 seconds.
- A change to a standalone poll or event refreshes the dashboards of its organizer and the people with access through its link only.

#### F-22 Dashboard (Core)

**User goal:** See at a glance where one needs to respond or get something done.

**Rules:**
- The dashboard shows these sections in this order:
  1. "Waiting for you": open polls without a complete response and events with the status "pending".
  2. "Polls": open polls the person may see, each with the own status "answered", "partly answered" or "not answered", plus a "Show all" link to "All polls" (F-31) and a "Create" button "New poll" that leads to the placement picker ("Standalone" or one of the own groups) for a new poll.
  3. "Übernahmen": one's own claims (things and tasks) on upcoming lists.
  4. "Events": upcoming events, full width, compact rows, with one's own acceptance, plus a "Show all" link to "All events" (F-32) and a "Create" button "New event" that leads to the placement picker ("Standalone" or one of the own groups) for a new event.
  5. "Lists": active lists the person may open, plus a "Show all" link to "All lists" (F-28) and a "Create" button "New list". Past lists are missing; event lists show only while the event is not past.
  6. Groups: the groups the person is a member of, the guest groups, and the card "Without groups" (F-29). A guest group is a group whose items the person reached only through an event or poll invite link (F-26, F-27). It carries a guest mark and shows only counts of the polls, events and lists the person may open; it opens the restricted group page (F-26). A person who is a member of the group sees it as a normal group. "Without groups" appears only when the person owns at least one poll, event or list without a group.
- Every section has the same structure: a title, an icon-only collapse toggle to the left of the title and, where applicable, a "Show all" link and a "Create" button.
- A section without content starts collapsed and a section with content starts expanded. A section that gains or loses content through a live update (F-21) switches to that state.
- Every section without content shows the same empty state, modelled on the empty state of "Waiting for you": a check icon, a short heading and a short text.
- Wherever polls, events and lists appear together, they appear in the order polls, then events, then lists. This applies to the dashboard sections and the counts on group cards, the group page, the restricted group page, the "Without groups" page, the event page (follow-up polls, then child events, then lists) and search results (F-25).
- The sections show the person's own standalone polls and events (F-29) like those of a group, without a group name.
- Poll rows show the answer deadline (F-08) when one is set.
- "Polls" also shows follow-up polls (F-12) to the people who see them. Child events get no rows of their own; they appear on their parent event page.
- Past events and lists are missing from the dashboard and stay reachable through group and event.
- A poll the person withdrew from (F-08) is missing from every section and from the open poll count of its group card. An event they withdrew from (F-11) is missing from "Waiting for you" and the upcoming events.

**Acceptance criteria:**
- An open poll in which a person answered two of five options appears under "Waiting for you" and, with the status "partly answered", under the current polls.
- After accepting, the event disappears from "Waiting for you" and appears under the upcoming events.
- With nothing waiting, "Waiting for you" shows the empty state with a check icon, a heading and a text.
- A section without content, for example "Lists" without active lists, shows the same empty state as "Waiting for you".
- Every dashboard section shows an icon-only collapse toggle to the left of its title.
- The dashboard shows the sections "Polls", "Events" and "Lists" in this order, and a group card shows its counts in the order polls, events, lists.
- A child event has no dashboard row. An open follow-up poll shows under "Polls" for an accepted participant of its event and not for a person with "pending".
- A list of a past event is missing from the lists section.
- The sections "Polls", "Events" and "Lists" show their "Show all" link and their "Create" button also when they are empty, and the targets of these links open.
- Collapsing a section hides its content; the content shows again after expanding.- After joining one event of a group through its link, the group shows under "Groups" with the guest mark and the counts 0 for polls, 1 for events and 0 for lists the person may not open.
- A group with three polls of which the person may open one shows the poll count 1 on the guest card.
- After the person joins the group as a member (F-04), the guest mark disappears from its card and the group shows its full counts.
- Without an own poll, event or list without a group, the card "Without groups" is missing.
- An own standalone poll and event appear under "Polls" and "Events" for the organizer and for nobody outside its link.
- After withdrawing from a poll, it is missing from "Waiting for you", from the current polls and from the open poll count of its group card.

#### F-23 Notifications (Secondary)

**User goal:** Learn what has happened without checking everything.

**Rules:**
- A bell in the header shows in-app notifications for: invitation to a group, new poll, new event, fixed date, moved event, new comment in a list with one's own claim, claim or release by another person on one's behalf, membership request of a guest (to the group owner).
- New notifications appear live. The person marks them as read.
- The app sends no emails for these, except the summary email for fixed dates (F-30).

**Acceptance criteria:**
- After fixing a date, the bell shows an unread notification to every accepted person.
- "Mark all as read" sets the counter to zero.
- Creating a standalone poll or event notifies no group member.

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
- From two letters on, the palette also searches the titles of groups, polls, events and lists, grouped by type with a heading in this order (F-22). Each type shows at most five results.
- Results only contain records the person may open: their groups, the polls and events of those groups by the rules of F-06, F-10 and F-12, their own standalone polls and events (F-29), the events and polls reached through an invite link (F-26, F-27) with their group as guest group, and the lists by the rules of F-13.
- The arrow keys move through the entries and Enter opens the selected one. While searching, the palette shows a loading state; without results it says so.

**Acceptance criteria:**
- Searching requires a login.
- An empty query or a single letter returns no records, only the quick actions.
- "gig" finds the group, poll, event and list with "gig" in their title, each with its heading and link.
- A poll, event or list of a group the person is not a member of never appears, unless the person reached it through an invite link or by the rules of F-13.
- An event list or follow-up poll appears only once the person takes part in the event.
- With seven matching polls, the palette shows five.
- The palette shows the headings in the order groups, polls, events, lists.
- An own standalone poll and event appear for the organizer and for nobody else without a link.

#### F-26 Event invite link (Secondary)

**User goal:** Bring people into a single event without adding them to the group.

**Rules:**
- The event page header carries a "Share" button that opens a dialog. The dialog shows the invite link with "Copy". Organizer, owner and admins of an event create one invite link per event there, copy it, regenerate it and revoke it. The page has no separate invite link section. Other people see no "Share" button. The old link shows "Not found" after revoking or regenerating. People who already joined keep their access.
- The link is always open. A logged-in account joins with one click. A guest joins with a name only, or logs in through the regular login (F-01): email with login link, password or passkey. A password or passkey login joins at once and lands on the event. The join page warns that a name-only account loses access at logout.
- The hierarchy is: poll, the events fixed from it (F-09), their child events and follow-up polls (F-12), and the lists of an event. Joining through any invite link (this one or F-27, including the link of a follow-up poll or a child event) grants access to the topmost ancestor of the shared item, without group membership: a child event or follow-up poll leads to its source event, an event fixed from a poll leads to that poll, and the chain continues up to an item without a parent. The guest opens that ancestor and everything below it by the existing rules. Follow-up polls and lists follow the participation rules of F-11 to F-13 as for members.
- The guest also keeps direct access to the shared item itself and lands on it. A follow-up poll shared by its own link is answered at once, even without accepting the source event (F-12).
- A link guest accepts or declines, comments, answers follow-up polls and claims items like a member. They never edit or delete, never see the group's other items or its members, and see the group only as the restricted group page.
- The event shows link guests among its participants. The event belongs to its real group, which the link guest sees as a guest group under "Groups" (F-22). A standalone event (F-29) has no group: only its organizer and its link guests open it, and it shows no guest group. Their events and polls appear in "Waiting for you" and the dashboard sections of F-22 where those rules apply, and in search (F-25).

**Acceptance criteria:**
- A member without the manager role cannot create, regenerate or revoke the link and sees no "Share" button on the event.
- The organizer opens the "Share" dialog in the event header, creates the link and copies it from the dialog.
- A guest who joins with a name only lands on the event and stays logged in on this browser.
- A guest who joined with a name only stays logged in after the session ended.
- A guest who joins with an email gets access only after following the login link.
- A link guest opens a child event, and after accepting the event its follow-up polls and lists.
- A child event link guest lands on the child event and opens its parent event, and the poll the parent was fixed from, if any.
- A link guest sees no event outside the granted ancestor chain, neither on the restricted group page nor by direct address (it shows "Not found").
- The restricted group page shows the group name and only the polls, events and lists the person may open, in this order. It shows no members list, no settings, no "Share" button and no actions "New poll", "New event" and "New list".
- Items outside the person's access never appear on the restricted page, not even their count or title.
- Opening a group without access to any of its items shows "Not found".
- A link guest never edits the group or its items.
- An owner or admin who invites the link guest as a member (F-04) turns the guest into a member; the guest mark disappears and the full group page opens.
- After revoking or regenerating, the old link shows "Not found".
- A link guest can accept the event but not edit it.
- A standalone event shows "Not found" to everyone except its organizer and its link guests; a link guest opens it after joining, without edit rights.
- An account with a password or passkey logs in on the join page and lands on the event with access.

#### F-27 Poll invite link (Secondary)

**User goal:** Let people answer a single poll without adding them to the group.

**Rules:**
- The poll page header carries a "Share" button that opens a dialog, as in F-26. Organizer, owner and admins of a poll create, copy, regenerate and revoke one invite link per poll there. Other people see no "Share" button.
- Joining follows F-26: one click when logged in, otherwise name only or the regular login (F-01) with login link, password or passkey; a password or passkey login joins at once and lands on the poll.
- Joining grants access to the topmost ancestor of the poll by the rule of F-26 and to everything below it under the same participation rules (F-11 to F-13). For a poll that is the topmost poll, its fixed events with their child events, follow-up polls and lists. For a follow-up poll it is the source event and the poll that event was fixed from, if any. The guest also keeps direct access to the poll itself and lands on it. A link guest belongs to the poll's respondents and counts in totals and the best option. A link guest of a follow-up poll answers it at once, even without accepting the source event (F-12).
- A link guest answers the poll but never edits, closes or fixes it, and sees the group only as the restricted group page (F-26).
- A person who withdrew from the poll (F-08) and joins again through its link is back in the audience and opens the poll.
- A standalone poll (F-29) has no group: only its organizer and its link guests open it, and it shows no guest group.

**Acceptance criteria:**
- A logged-in account without group membership answers the poll right after joining through the link.
- A link guest opens an event fixed from the poll, but no other event of the group.
- A revoked link shows "Not found".
- A link guest opens a child event of a fixed event, and after accepting the fixed event its follow-up polls and lists.
- A guest who joins through the link of a follow-up poll lands on the follow-up poll, opens the source event and the poll the source event was fixed from, and answers the follow-up poll at once without accepting the source event.
- The poll owner opens the "Share" dialog in the poll header, creates the link and copies it from the dialog.
- The poll and its fixed events appear on the restricted group page of their group (F-26), in the dashboard sections of F-22 and in search.
- The restricted group page shows the poll and its fixed events, and no other poll or event of the group.
- A member who withdrew from the poll and joins through its link opens the poll again.
- A standalone poll shows "Not found" to everyone except its organizer and its link guests; a link guest opens it after joining, without edit rights.
- An account with a password or passkey logs in on the poll's join page and lands on the poll with access.

#### F-28 All lists (Secondary)

**User goal:** Find any list one may open, also past ones, in one place.

**Rules:**
- The page "All lists", reached from the dashboard link (F-22), shows a table of all lists the person may open by the rules of F-13, active and past.
- Filters: "My lists only" (the person owns the list or has a claim on it), "Tasks only", and a switch between active and all lists. The default is active.
- Lists the person may not open never appear.

**Acceptance criteria:**
- A past list appears only after switching to "all".
- "My lists only" hides a list that the person neither owns nor has a claim on.
- "Tasks only" hides a list without tasks.
- A list of a group the person is not a member of never appears.

#### F-29 Without groups (Secondary)

**User goal:** Reach one's own polls, events and lists that belong to no group.

**Rules:**
- Polls, events and lists can be standalone, without a group. A standalone poll or event is visible only to its organizer and to people who joined through its invite link (F-26, F-27); only the organizer manages it.
- The dashboard shows the virtual group "Without groups" as a card in the groups section (F-22). It appears only when the person owns at least one poll, event or list without a group. It appears nowhere else: the placement picker when creating a poll, event or list offers "Standalone", not "Without groups".
- Its page lists only the person's own standalone polls, events and lists, in this order (F-22), follow-up polls and child events excluded. Items reached through invite links (F-26, F-27) do not appear there, also not standalone ones of other people.
- Deleting a standalone poll or event returns to the dashboard.
- Hidden items stay "Not found".

**Acceptance criteria:**
- An own standalone poll and event appear on the page "Without groups"; a standalone poll or event of another person joined through its link does not.
- A list of the person without a group appears on the page "Without groups"; a list of another person does not.
- The page "Without groups" shows polls before events and events before lists.
- Without an own poll, event or list without a group, the dashboard shows no card "Without groups".
- After joining an event through its link, the event does not appear on the page "Without groups".
- The placement picker when creating a poll, event or list offers "Standalone" and the person's own groups, never "Without groups".
- Deleting a standalone poll or event leads to the dashboard with a confirmation message.
- Opening an item of a revoked or unreached link shows "Not found".

#### F-30 Email on fixed dates (Secondary)

**User goal:** Learn the final date without opening the app.

**Rules:**
- When an organizer fixes one or more dates of a date finder (F-09), the app queues one summary email per recipient that lists all fixed dates in calendar order, however many dates were fixed.
- Recipients are the accepted and pending participants (F-09) with an email address. The person who fixed the dates gets no email, and neither does a person who may not view the event (section 8).
- People without an email address are skipped. Without a remaining recipient, no email is sent.
- The email is in the recipient's interface language (F-24) and links to the poll. The bell notification (F-23) stays as is.
- A rejected fixing sends no email.

**Acceptance criteria:**
- After fixing one date, each accepted and pending participant with an email receives one email; declined, unanswered people and the acting person receive none.
- After fixing three dates, each recipient receives one email that lists all three dates in calendar order.
- A participant without an email address receives nothing and fixing still succeeds.
- Fixing a date with no recipient left sends no email.
- A recipient with language German receives the email in German, one with English in English.
- A closed poll, an option of another poll, an already fixed option or a missing permission sends no email.
- A participant who left the group before the fixing receives no email.

#### F-31 All polls (Secondary)

**User goal:** Find any poll one may open, also closed ones, in one place and start a new poll from there.

**Rules:**
- The page "Polls" shows the polls the person may open by the rules of F-06 and F-22, including their own standalone polls (F-29) and the follow-up polls they see (F-12): polls they withdrew from (F-08) are left out. The default is open polls.
- Filters: "Unanswered" (the person answered none or only some options), "My polls only" (the person organizes the poll), and a switch between open and all polls, which adds closed ones.
- Filters combine. The page has a button "New poll" that leads to the placement picker ("Standalone" or one of the own groups) for a new poll.
- Polls the person may not open never appear.
- Rows show the answer deadline (F-08) when one is set.

**Acceptance criteria:**
- A closed poll appears only after switching to "all".
- "Unanswered" shows a poll with no or partial answers and hides a fully answered one.
- "My polls only" hides a poll that another person organizes.
- A poll of a group the person is not a member of, a poll the person withdrew from and a follow-up poll of an event the person has not accepted never appear.
- A follow-up poll appears for an accepted participant of its event.
- Active filters combine with AND.
- An own standalone poll appears; a standalone poll of another person without a link does not.

#### F-32 All events (Secondary)

**User goal:** Find every event one takes part in, also past ones, in one place and start a new one from there.

**Rules:**
- The page "All events" shows the events the person may open by the rules of F-22 (events of their groups, their own standalone events (F-29) and events reached through an invite link) and where their participation is "accepted" or "pending". Withdrawn and declined events and child events do not appear.
- Filters: "My events only" (the person accepted), and a switch between upcoming and all events. The default is upcoming.
- The button "New event" leads to the placement picker ("Standalone" or one of the own groups) for creating an event.

**Acceptance criteria:**
- Events of a group the person is not a member of never appear.
- A past event appears only after switching to "all".
- "My events only" hides an event the person has not accepted yet.
- An event the person declined or withdrew from, and a child event, does not appear.
- An own standalone event appears; a standalone event of another person without a link does not.

#### F-33 Privacy policy and legal notice (Secondary)

**User goal:** Learn who runs the app and how it handles personal data, without logging in.

**Rules:**
- The privacy policy at /privacy and the legal notice at /legal-notice are public. Guests see them in the website layout, logged-in accounts in the app layout.
- The site footer links to both pages, so the start page links to both.
- Both texts exist in English and German and follow the language switch (F-24). They use formal legal language, and the German text uses the formal address. The German pages are titled "Impressum" and "Datenschutzerklärung".
- The operator's name has a configured default, the site owner. Address and email come from the configuration only (LEGAL_OPERATOR_NAME, LEGAL_OPERATOR_ADDRESS, LEGAL_OPERATOR_EMAIL). The legal notice shows only the values that are set. The privacy policy names the operator's email as contact when it is configured, otherwise it refers to the operator named in the legal notice.
- The privacy policy is short and has seven blocks: controller, personal data processed, purpose and legal basis, recipients, cookies and local storage, retention and deletion, and data subject rights under the revFADP and GDPR including the complaint to the Swiss FDPIC or the local authority.
- The data covered are email, name, language, the password stored only as a hash, for each passkey its public key, the name the person gave it and the date of last use, the personal calendar feed link (F-34), entered content, and the email address or name that other people enter about a person when they invite them or add them as a friend. Technical data are the IP address and browser details in login sessions and server logs.
- The policy names the only three kinds of emails: login link, email address confirmation link and the summary of fixed dates (F-30). Cookies and browser storage are strictly necessary, with no tracking and no third-party content. Browser storage consists of the theme setting and the technical page state of the framework, both strictly necessary.
- The policy states who sees what: other users of the person's groups, lists, polls and events and people who hold a share or invite link see the person's name, while only group owners and admins see email addresses.
- Data stays while the account exists, and hosting and email providers process it on behalf of the operator. Because the app has no account deletion yet, a person asks for deletion by email to the operator.

**Acceptance criteria:**
- A guest and a logged-in account can open the privacy policy and the legal notice.
- Both pages receive the operator's name, address and email from the configuration.
- With no operator details configured, both pages still open and receive no operator details.

#### F-34 Calendar feed (Secondary)

**User goal:** See own events and reserved poll dates in the personal calendar app.

**Rules:**
- In the settings, the person turns on a personal feed link, copies it or subscribes through a webcal link, regenerates it (the old link stops working) and turns it off.
- The feed works without login. Anyone with the link reads its content, so the settings hint says to keep the link private.
- Events the person can see with participation "accepted" appear as confirmed, and events with participation "pending" appear as tentative. Child events count like other events.
- Each option of an open poll that the person answered "yes" or "maybe" appears as a tentative entry titled "Reserved: {poll title}" (German "Reserviert: {poll title}") as long as the option is not fixed. This applies to date finder and attendance polls. Closing the poll, fixing the option or withdrawing from the poll removes the entry.
- Timed entries carry the person's time zone (F-02), and the feed declares it as the calendar's time zone. Options and events without a start time appear as all-day entries. Entry texts follow the person's language (F-24).

**Acceptance criteria:**
- An unknown or revoked link returns 404, and after regenerating, the old link returns 404.
- An accepted event appears as confirmed, a pending one as tentative, and a declined one does not appear.
- An event the person no longer has access to does not appear.
- A "yes" option and a "maybe" option of an open poll appear as reserved, and a "no" option does not.
- Closing the poll or fixing the option removes the reserved entry. The fixed event appears instead when the person accepted it or has it pending.
- An option without a time appears as an all-day entry.
- For a person in Europe/Zurich, an event at 19:00 Zurich time appears at 19:00 with time zone Europe/Zurich, and the feed declares Europe/Zurich.

## 6. Structure

**Navigation:** header with logo (leads to the dashboard), search and quick actions (F-25), notifications (F-23) and user menu (settings, friends, logout). There is no permanent sidebar; groups, events and lists link to each other on their pages.

**Pages:**
- **Start page:** public page with explanation, login form (F-01) and language switch (F-24).
- **Dashboard:** F-22, actions "New group", "New poll", "New event" and "New list", links "All polls", "All events" and "All lists".
- **All polls:** list of all polls the person may open, with filters and the button "New poll" (F-31).
- **All events:** list of all events the person may open, with filters and the button "New event" (F-32).
- **All lists:** table of all lists the person may open, with filters (F-28).
- **Without groups:** virtual group page with the person's own polls, events and lists without group (F-29).
- **New group:** name and invitations (F-03, F-04).
- **Group:** header action bar with "Share" (F-04), members with invite (F-04), polls, upcoming and past events, group lists, actions "New poll", "New event" and "New list". Link guests see a restricted version with the group name and only the items they may open, without members, settings, "Share" and create actions (F-26, F-27).
- **Group settings:** name, option, membership requests, roles, delete (F-03, F-04).
- **Poll:** response grid, best option, fix dates, "Share" in the header action bar (F-06, F-07, F-09, F-27).
- **Poll edit:** the "New poll" page in edit mode with the same components: title, options, close, delete (F-08).
- **Event:** key details, participation with one's own accept or decline, follow-up polls, child events, lists, "Share" in the header action bar, edit (F-10 to F-12, F-26).
- **Event edit:** the "New event" page in edit mode with the same components, delete (F-10).
- **List:** items with claims, comments, attendance day, "Share" in the header action bar (F-13 to F-15, F-18 to F-20).
- **List edit:** the "New list" page in edit mode with the same components: title, description, date, add setting, custom fields, attendance link, duplicate, delete (F-14, F-16 to F-18).
- **Shared list:** view for guests with login on the same page (F-19).
- **New poll:** title, type, granularity, option calendar and option rows, audience note; from a group or, as a follow-up, from an event (F-06, F-07, F-12).
- **New event:** title, date, time, end date, end time, location, description, audience note (F-10).
- **New list:** title, description, date, placement, add setting (F-13, F-14).
- **Settings** and **Friends:** F-02, F-05, language switch (F-24).
- **Privacy policy** and **Legal notice:** public pages at /privacy and /legal-notice, linked from the site footer (F-33).

Editing items happens in a dialog. Creating a group, a poll, an event or a list has its own page, reached from the group, the event or the dashboard, with a cancel action that returns to where the person came from.

**Main flows:**
1. **Find a gig date:** open the group, "New poll", create a date finder with four dates. Members respond. The organizer chooses "Fix date", selects one date and confirms. The event is created with the acceptances.
2. **Rehearsals only with those who accepted:** open the event, "Follow-up poll", date finder with time slots on several evenings. Only accepted participants respond. The organizer fixes three slots; three rehearsals appear under the event.
3. **Distribute tasks and things to bring:** open the event, "New list", enter items such as "PA system", "Extension reel" or "Drinks, 2 crates". Participants claim; the organizer assigns the remaining items.
4. **Single date:** open the group, "New event" with date and time. Members accept or decline on the dashboard or in the event.
5. **Decline after accepting:** open the event, open the status chip, "Decline…", confirmation with one's own claims, confirm. The items are free again.
6. **Taking part from outside:** open the list, enable the share link and share it. A guest claims an item and logs in on the same page.

## 7. Data

- **Account:** email (text, required, unique), name (text, required from the first login), password (optional), language (choice: English, German, required), time zone (IANA zone, F-02), calendar feed link (text, optional, F-34).
- **Friend:** account (required), befriended account (required), display name (text).
- **Group:** name (text, required), owner (account, required).
- **Membership:** group, account, role (choice: owner, admin, member, required), accepted at (date, empty until the first login).
- **Membership request:** group, account (both required). At most one per account and group; removed when the owner accepts or declines (F-03).
- **Poll:** group (optional; standalone without one, F-29), organizer (account, required), title (text, required), type (choice: date finder, attendance, required), mode (choice: by day, time slots, required), closed at (date), event (optional, for follow-up polls), answer deadline (UTC timestamp, optional), auto-close (yes/no).
- **Poll option:** poll (required), date (required for by-day options), start and end (UTC timestamps, required for time slots), label (text), fixed (yes/no).
- **Response:** option, account, answer (choice: yes, maybe, no, required), note (text, optional, at most 200 characters). At most one response per account and option.
- **Poll withdrawal:** poll, account (both required). At most one per account and poll (F-08).
- **Event:** group (optional; standalone without one, F-29), organizer (account, required), title (text, required), description (text), location (text), start (date, or UTC timestamp when it has a time, required), end (date or UTC timestamp, required, defaults to the start), origin (poll option, optional), parent event (optional, one level).
- **Participation:** event, account, status (choice: accepted, pending, declined, required). At most one participation per account and event.
- **List:** title (text, required), description (text), owner (account, required), group or event (each optional, at most one), date (date), members can add items (yes/no), share link (text, optional), attendance day (option of an attendance poll, optional).
- **Item:** list (required), name (text, required), quantity (number, optional), unit (text), notes (text), date (date), attendance day (optional), custom field values.
- **Claim:** item, account, quantity (number, required for items with a quantity). At most one claim per account and item.
- **Custom field:** list (required), name (text, required).
- **Comment:** list, item, event or poll (exactly one, required), author (account, required), text (required), timestamp.
- **Notification:** account, type (choice per F-23), reference, read at (date).
- **Access grant:** account (required), event or poll (exactly one, required), joined via (link). At most one grant per account and event or poll (F-26, F-27). A join through any invite link, or a login through a list share link (F-19), creates a grant for the topmost ancestor and one for the shared item itself. Events and polls carry an optional invite link.

Groups, lists, items, polls, events and custom fields appear in URLs with random identifiers instead of sequential numbers, so nobody finds other people's content by trying numbers.

## 8. States and edge cases

- **No groups and lists:** the dashboard explains both entry points and offers "New group" and "New list".
- **Nothing waiting:** "Waiting for you" shows the common empty state with a check icon and the heading "All caught up." (F-22).
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
- **Event moved:** the edit form asks whether to reset acceptances to "pending" or keep them; claims stay in both cases.
- **Decline without claims:** no confirmation needed.
- **Status set for another person:** claims stay, also on "declined".
- **Withdrawn from a poll or event:** the poll shows "Not found" to the person; the event stays reachable for group members and link guests without a participation status (F-08, F-11).
- **Claim above the remaining quantity:** rejected with the remaining quantity in the message.
- **No access:** a list, poll or event without permission shows "Not found", so its existence stays hidden. An event or poll invite link gives permission for the topmost ancestor of the shared item, everything below it by the participation rules and the shared item itself, never for the group's other items or members. The group appears to the link guest as a restricted page with only the permitted items, and a group without any permitted item shows "Not found" (F-26, F-27).
- **Revoked event or poll invite link:** the join page shows "Not found"; people who joined keep their access.
- **Invalid share link:** shows a note that the link is no longer valid.
- **Expired login link:** shows a note with the action "Request new link".
- **Attendance link without an attendance poll in the group:** the field does not appear.
- **Live update connection lost:** the app shows a note and refreshes after reconnecting.
- **Link in free text:** a web address (http, https or www) in an event or list description, an item note or a comment shows as a link that opens in a new tab. Sentence punctuation after the address stays outside the link, and other schemes such as javascript: stay plain text.

## 9. Non-functional requirements

- **Language:** English and German (F-24). Dates follow the active language: 15.10.2026 in German, 15 Oct 2026 in English. Times in 24-hour format in both.
- **Time zones:** the app stores times as UTC and shows them in the viewer's time zone. That is the account setting when logged in and the browser's time zone otherwise, for example on invite join pages. Entered times count in the time zone of the person entering them, and emails (F-30) use the recipient's time zone. Dates without a time (all-day events, by-day options, item dates) carry no time zone and show the same day everywhere. A time slot appears on the day of its start in the viewer's time zone.
- **Devices:** mobile first from 375 px width for all functions, desktop up to 1440 px without functional differences. No horizontal scrolling except in the poll's response grid.
- **Accessibility:** WCAG 2.2 level AA, including the response grid.
- **Legal:** Swiss Federal Act on Data Protection (revFADP) and GDPR, privacy policy and legal notice as public pages (F-33).
- **Volume:** groups up to 50 members, polls up to 60 options, lists up to 200 items. Expected usage: about 5 groups per account and about 5 polls, events and lists each per group per year. Pages load in under 1 second, live updates appear in under 2 seconds.
- **Hosting:** the app runs on https under bringathing.com behind one TLS-terminating proxy. Production forces https and the host of APP_URL for generated links, and the session cookie is secure. By default only the connecting proxy is trusted for client IPs. Passkeys are bound to the host of APP_URL. The www host must redirect to the apex host at the proxy or DNS level.

## 10. Out of scope

- Two-way calendar synchronization and importing from calendars
- Chat beyond comments
- Costs, fees and splitting expenses
- Setlists and other occasion-specific content
- Native apps
- Poll responses without an account; an invite link guest always gets an account, if only with a name (F-26, F-27)
- Recurring events and automatically recurring lists (duplication only)
- Emails other than the login link, the email address confirmation link and the summary of fixed dates (F-30), including reminders
- Status fields for lists and items; past is derived from the date
- More than one level of child events
- **Later:** QR codes for share and invite links, automatic deletion of old polls and lists, list expiry after the event, Excel export of poll results, event participation and list items.

## 11. Open questions

- **Logout on one device:** logging out ends the remembered login of the account on its other devices as well, because the account holds a single remember token. Per-device tokens would avoid this (F-01).
- **Account deletion by the person themselves:** the privacy policy sends deletion requests to the operator by email because the app has no self-service account deletion (F-33).

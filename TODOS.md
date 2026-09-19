# TODOS

## Notes

Keep `BRING_A_THING.md` and `IMPLEMENDATION.md` in sync after implementing a todo.

## Pending

| ID | Added | Description |
|----|-------|-------------|
| 5 | 2026-09-16 14:53 | Add a simple feedback feature, that enables admins/owners to send feedback to the platform admins/developers. It should track how satisfied the users are, what they like (optional) and if they have an idea to improve the app (optional). Then, the platform admin can manage those responses and either set the state to "planned" or "rejected". The user cannot see their responses yet. The platform admin needs to have a global `is_super_user` setting, so that these users can also manage other parts of the platform later (full-access to all posters, polls and user list). |
| 8 | 2026-09-18 10:29 | Comments should be able to be sent with meta + enter immediately. |

## Completed

| ID | Added | Completed | Commit | Description |
|----|-------|-----------|--------|-------------|
| 1 | 2026-09-16 11:43 | 2026-09-16 14:07 | 6ee50eb | Change URL IDs to slugs (similar to shared tokens) to prevent guessing URL incremental numbers. |
| 2 | 2026-09-16 11:43 | 2026-09-16 14:07 | 6ee50eb | Add roster admins: they can update roster settings, manage group members, and add things without the "members can add things" setting being enabled; group members can be made an admin (simple `is_admin` column in pivot table). |
| 3 | 2026-09-16 11:43 | 2026-09-16 14:07 | 6ee50eb | Owners and admins can unclaim a thing from another person and also claim it for another person. |
| 4 | 2026-09-16 11:43 | 2026-09-16 14:07 | 6ee50eb | Add a friends table that saves all invited members of an account, making it easier to re-reference those accounts instead of typing their email addresses each time. |
| 6 | 2026-09-18 10:29 | 2026-09-18 10:36 | 4e0f632 | Remove the extra "(optional)" tags on form elements; mark required fields with an asterisk instead, and move any extra field details into a description line. Recorded as a project guideline in `.ai/rules/js.md`. |
| 7 | 2026-09-18 10:29 | 2026-09-19 14:35 | 5b526f5 | The non-attendance warning is rendering, but it should appear as a modal instead: when clicking "I'll bring this" it should show a confirmation modal to confirm bringing the item despite being not available. |
| 9 | 2026-09-19 14:35 | 2026-09-19 14:35 | 5b526f5 | If an item/roster has an attendance day set, it should always show that link (works for the day field already) and provide a link to the poll so that the user could change their vote easily. Added on request while implementing todo #7, beyond its original scope. |

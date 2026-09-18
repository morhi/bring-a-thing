This is a list of ideas and todos. Keep the @BRING_A_THING.md and @IMPLEMENTATION.md in sync after changing something:

TOOO:
- remove the extra (optional) tags on all form elements and mark required fields with an asterics instead. if a field requires more details write it as a field description instead. add this as a project guideline.
- the non-attendency warning is rendering, but it should appear as a modal instead: when i click "I'll bring this" it shows a confirmation modal to confirm that i want to bring the item despite being not available
- Comments should be able to be sent with meta + enter immediately
- Add a simple feedback feature, that enables admins/owners to send feedback to the platform admins/developers. It should track how satiesfied the users are, what they like (optional) and if they have an idea to improve the app (optional). Then, the platform admin can manage those response and either set the state to "planned" or "rejected". The user cannot see their responses yet. The platform admin needs to have a global is_super_user setting, so that these users can also manage other parts of the platform later (full-access to all posters, polls and user list).

DONE:
- Change URL IDs to Slugs (similar to shared tokens) to prevent guessing URL incremental numbers
- We need roster admins: they can update roster settings, manage group members, add things without the roster members can things switch enabled; for this group members can be made an admin (simple is_admin col in pivot table should be enough)
- owners and admin must be able to unclaim a thing from another person and also claim it for another person
- add a friends table, that saves all invited members of an account and makes it easier to re-reference those accounts instead of typing their email addresses all the time

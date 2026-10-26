// Group settings page (F-03, F-04): name, members with roles, invite and remove, delete group.
// Owner and admin reach it from the group page; the admin sees no role change and no delete action.

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, toast, nextId } from '../core.js';
import { t } from '../i18n.js';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const roleOrder = { owner: 0, admin: 1, member: 2 };

function personName(id) {
  return h.displayName(id) || h.account(id).email;
}

function deleteList(listId) {
  const itemIds = h.items(listId).map((item) => item.id);
  data.claims = data.claims.filter((claim) => !itemIds.includes(claim.itemId));
  data.comments = data.comments.filter((comment) => comment.listId !== listId);
  data.customFields = data.customFields.filter((field) => field.listId !== listId);
  data.items = data.items.filter((item) => item.listId !== listId);
  data.lists = data.lists.filter((list) => list.id !== listId);
}

function deletePoll(pollId) {
  const optionIds = h.options(pollId).map((option) => option.id);
  data.responses = data.responses.filter((response) => !optionIds.includes(response.optionId));
  data.pollOptions = data.pollOptions.filter((option) => option.pollId !== pollId);
  data.polls = data.polls.filter((poll) => poll.id !== pollId);
}

function deleteEvent(eventId) {
  data.events.filter((event) => event.parentEventId === eventId).forEach((child) => deleteEvent(child.id));
  h.listsOfEvent(eventId).forEach((list) => deleteList(list.id));
  data.polls.filter((poll) => poll.eventId === eventId).forEach((poll) => deletePoll(poll.id));
  data.participations = data.participations.filter((entry) => entry.eventId !== eventId);
  data.events = data.events.filter((event) => event.id !== eventId);
}

// Deleting a group takes its polls, events and lists with everything attached (F-03).
function deleteGroup(groupId) {
  h.eventsOf(groupId).forEach((event) => deleteEvent(event.id));
  h.pollsOf(groupId).forEach((poll) => deletePoll(poll.id));
  h.listsOfGroup(groupId).forEach((list) => deleteList(list.id));
  data.memberships = data.memberships.filter((membership) => membership.groupId !== groupId);
  data.groups = data.groups.filter((group) => group.id !== groupId);
}

const DeleteGroupDialog = {
  props: { group: { type: Object, required: true }, counts: { type: Object, required: true } },
  emits: ['close', 'confirm'],
  setup() {
    return { t };
  },
  template: `
    <AppDialog :title="t('groupSettings.deleteTitle', { group: group.name })" :subtitle="t('groupSettings.deleteText')" @close="$emit('close')">
      <div class="row" data-feature="F-03">
        <span class="badge">{{ t('groupSettings.deleteMembers', { n: counts.members }) }}</span>
        <span class="badge">{{ t('groupSettings.deletePolls', { n: counts.polls }) }}</span>
        <span class="badge">{{ t('groupSettings.deleteEvents', { n: counts.events }) }}</span>
        <span class="badge">{{ t('groupSettings.deleteLists', { n: counts.lists }) }}</span>
      </div>
      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="button" class="btn btn--danger" @click="$emit('confirm')">{{ t('groupSettings.delete') }}</button>
      </template>
    </AppDialog>`
};

export const GroupSettingsView = {
  components: { DeleteGroupDialog },
  setup() {
    const user = currentUser;
    const group = computed(() => h.group(ui.currentGroupId));
    const role = computed(() => (group.value ? h.roleIn(group.value.id, user.value.id) : null));
    const isOwner = computed(() => role.value === 'owner');
    const canManage = computed(() => ['owner', 'admin'].includes(role.value));

    const members = computed(() => {
      if (!group.value) {
        return [];
      }

      return [...h.memberships(group.value.id)].sort((a, b) => roleOrder[a.role] - roleOrder[b.role] || personName(a.accountId).localeCompare(personName(b.accountId)));
    });

    const counts = computed(() => {
      if (!group.value) {
        return { members: 0, polls: 0, events: 0, lists: 0 };
      }

      return {
        members: members.value.length,
        polls: h.pollsOf(group.value.id).length,
        events: h.eventsOf(group.value.id).length,
        lists: h.listsOfGroup(group.value.id).length
      };
    });

    const name = ref(group.value ? group.value.name : '');
    const nameError = ref('');
    const removing = ref(null);
    const deleting = ref(false);

    function saveName() {
      if (!name.value.trim()) {
        nameError.value = t('newGroup.nameRequired');

        return;
      }

      group.value.name = name.value.trim();
      nameError.value = '';
      toast(t('groupSettings.nameSaved'));
    }

    function setRole(membership, value) {
      membership.role = value;
      toast(t(`groupSettings.roleChanged.${value}`, { name: personName(membership.accountId) }));
    }

    function remove(membership) {
      data.memberships = data.memberships.filter((entry) => !(entry.groupId === membership.groupId && entry.accountId === membership.accountId));
      removing.value = null;
      toast(t('groupSettings.removed', { name: personName(membership.accountId) }));
    }

    function confirmDelete() {
      deleteGroup(group.value.id);
      deleting.value = false;
      toast(t('groupSettings.deleted'));
      go('dashboard');
    }

    // Invite form with friend suggestions, same behavior as on the group page (F-04, F-05)
    const inviteEmail = ref('');
    const inviteError = ref('');
    const suggestionsOpen = ref(false);

    const suggestions = computed(() => {
      const query = inviteEmail.value.trim().toLowerCase();

      if (!query || !group.value) {
        return [];
      }

      const memberIds = h.membersOf(group.value.id);

      return h.friendsOf(user.value.id)
        .filter((friend) => friend && !memberIds.includes(friend.id))
        .filter((friend) => friend.email.toLowerCase().includes(query) || (friend.name || '').toLowerCase().includes(query))
        .slice(0, 4);
    });

    function pickSuggestion(friend) {
      inviteEmail.value = friend.email;
      suggestionsOpen.value = false;
    }

    function invite() {
      const email = inviteEmail.value.trim().toLowerCase();

      if (!emailPattern.test(email)) {
        inviteError.value = t('group.invite.invalid');

        return;
      }

      let account = data.accounts.find((entry) => entry.email.toLowerCase() === email);

      if (account && h.membersOf(group.value.id).includes(account.id)) {
        inviteError.value = t('group.invite.exists', { name: account.name || account.email });

        return;
      }

      if (!account) {
        account = { id: nextId('u'), name: '', email, language: ui.lang, tone: 5 };
        data.accounts.push(account);
      }

      data.memberships.push({ groupId: group.value.id, accountId: account.id, role: 'member', acceptedAt: null });

      if (!data.friends.some((entry) => entry.accountId === user.value.id && entry.friendId === account.id)) {
        data.friends.push({ accountId: user.value.id, friendId: account.id });
      }

      inviteError.value = '';
      inviteEmail.value = '';
      suggestionsOpen.value = false;
      toast(t('group.invite.sent', { email }));
    }

    return {
      user, group, isOwner, canManage, members, counts, name, nameError, removing, deleting,
      saveName, setRole, remove, confirmDelete,
      inviteEmail, inviteError, suggestions, suggestionsOpen, pickSuggestion, invite,
      personName, go, h, t
    };
  },
  template: `
    <main class="container page group-settings" v-if="group && canManage">
      <div class="gs-head" data-feature="F-03">
        <button type="button" class="back" @click="go('group', { currentGroupId: group.id })"><Icon name="back" /> {{ t('groupSettings.back', { group: group.name }) }}</button>
        <h1>{{ t('groupSettings.title') }}</h1>
        <p class="lede">{{ group.name }}</p>
      </div>

      <section class="section" aria-labelledby="gs-name-title" data-feature="F-03">
        <div class="section-head">
          <h2 id="gs-name-title">{{ t('groupSettings.name') }}</h2>
        </div>
        <form class="gs-name" v-if="isOwner" @submit.prevent="saveName" novalidate>
          <FormField :label="t('newGroup.name')" control="gs-name" required :error="nameError">
            <div class="gs-name__row">
              <input class="input" id="gs-name" v-model="name" :aria-invalid="nameError ? 'true' : null">
              <button type="submit" class="btn btn--primary">{{ t('groupSettings.saveName') }}</button>
            </div>
          </FormField>
        </form>
        <template v-else>
          <p class="gs-name__readonly">{{ group.name }}</p>
          <p class="notice">{{ t('groupSettings.nameOwnerOnly') }}</p>
        </template>
      </section>

      <section class="section" aria-labelledby="gs-members-title" data-feature="F-03">
        <div class="section-head">
          <h2 id="gs-members-title">{{ t('group.members') }}<span class="section-head__count">{{ members.length }}</span></h2>
          <span class="notice">{{ isOwner ? t('groupSettings.rolesHintOwner') : t('groupSettings.rolesHintAdmin') }}</span>
        </div>
        <div class="card gs-members">
          <ul class="rows">
            <li class="gs-member" v-for="membership in members" :key="membership.accountId">
              <Avatar :id="membership.accountId" />
              <span class="gs-member__main">
                <span class="gs-member__name">
                  <span v-if="h.displayName(membership.accountId)">{{ h.displayName(membership.accountId) }}</span>
                  <span class="soft" v-else>{{ t('common.noName') }}</span>
                  <span class="soft small" v-if="membership.accountId === user.id">({{ t('group.member.you') }})</span>
                  <span class="badge badge--warning" v-if="!membership.acceptedAt">{{ t('group.member.pending') }}</span>
                </span>
                <span class="gs-member__email">{{ h.account(membership.accountId).email }}</span>
              </span>
              <span class="gs-member__controls">
                <span class="gs-member__role" v-if="membership.role === 'owner'">{{ t('group.member.owner') }}</span>
                <template v-else>
                  <template v-if="isOwner">
                    <label class="visually-hidden" :for="'gs-role-' + membership.accountId">{{ t('groupSettings.roleOf', { name: personName(membership.accountId) }) }}</label>
                    <select class="select input--sm gs-role" :id="'gs-role-' + membership.accountId" :value="membership.role" @change="setRole(membership, $event.target.value)" data-feature="F-03">
                      <option value="admin">{{ t('group.member.admin') }}</option>
                      <option value="member">{{ t('group.member.member') }}</option>
                    </select>
                  </template>
                  <span class="gs-member__role" v-else>{{ t('group.member.' + membership.role) }}</span>
                  <template v-if="membership.accountId !== user.id">
                    <span class="gs-confirm" v-if="removing === membership.accountId">
                      <span class="small">{{ t('groupSettings.removeConfirm', { name: personName(membership.accountId) }) }}</span>
                      <button type="button" class="btn btn--danger btn--sm" @click="remove(membership)">{{ t('groupSettings.remove') }}</button>
                      <button type="button" class="btn btn--ghost btn--sm" @click="removing = null">{{ t('poll.settings.keep') }}</button>
                    </span>
                    <button v-else type="button" class="btn btn--ghost btn--icon btn--sm" :aria-label="t('groupSettings.removeLabel', { name: personName(membership.accountId) })" @click="removing = membership.accountId" data-feature="F-04"><Icon name="x" /></button>
                  </template>
                </template>
              </span>
            </li>
          </ul>
          <p class="notice">{{ t('groupSettings.ownerNote') }}</p>

          <form class="invite" @submit.prevent="invite" novalidate data-feature="F-04">
            <FormField :label="t('group.invite.title')" control="gs-invite-email" :error="inviteError" :hint="t('group.invite.hint')">
              <div class="invite__row">
                <input class="input" id="gs-invite-email" type="email" v-model="inviteEmail" :placeholder="t('group.invite.placeholder')" autocomplete="off" @focus="suggestionsOpen = true" @input="suggestionsOpen = true" :aria-invalid="inviteError ? 'true' : null" aria-autocomplete="list" aria-controls="gs-invite-suggestions" :aria-expanded="suggestionsOpen && suggestions.length > 0">
                <button type="submit" class="btn btn--primary"><Icon name="plus" /> <span class="visually-hidden">{{ t('group.invite.button') }}</span></button>
              </div>
              <div class="suggestions" id="gs-invite-suggestions" role="listbox" v-if="suggestionsOpen && suggestions.length" data-feature="F-05">
                <button type="button" class="suggestion" role="option" v-for="friend in suggestions" :key="friend.id" @click="pickSuggestion(friend)">
                  <Avatar :id="friend.id" size="sm" />
                  <span>{{ friend.name || t('common.noName') }}</span>
                  <span class="suggestion__email">{{ friend.email }}</span>
                </button>
              </div>
            </FormField>
          </form>
        </div>
      </section>

      <section class="section" aria-labelledby="gs-danger-title" v-if="isOwner" data-feature="F-03">
        <div class="section-head">
          <h2 id="gs-danger-title">{{ t('groupSettings.danger') }}</h2>
        </div>
        <div class="gs-danger">
          <p class="muted">{{ t('groupSettings.deleteHint') }}</p>
          <button type="button" class="btn btn--danger" @click="deleting = true">{{ t('groupSettings.delete') }}</button>
        </div>
      </section>

      <DeleteGroupDialog v-if="deleting" :group="group" :counts="counts" @close="deleting = false" @confirm="confirmDelete" />
    </main>
    <main class="container page" v-else>
      <div class="stack">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('group.notFound') }}</h1>
      </div>
    </main>`
};

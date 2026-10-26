// Group settings page (F-03, F-04): name, invite link, options, members with roles, invite and remove, delete group.
// Owner and admin reach it from the group page; the admin sees no role change, no rename, no options and no delete action.

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, toast, createInviteLink, inviteUrl, copyText } from '../core.js';
import { t } from '../i18n.js';
import { InviteField, inviteToGroup, invitedToast } from '../invite-field.js'; // new-group

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
  components: { DeleteGroupDialog, InviteField },
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

    // Removing ends the membership only; participations, claims and responses stay (F-04).
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

    // invite-link: one link per group with a lock setting; revoke and regenerate invalidate the old link (F-04)
    const linkUrl = computed(() => (group.value && group.value.inviteLink ? inviteUrl(group.value) : ''));
    const lockOptions = computed(() => [
      { value: 'open', title: t('groupSettings.link.openTitle'), hint: t('groupSettings.link.openHint') },
      { value: 'closed', title: t('groupSettings.link.closedTitle'), hint: t('groupSettings.link.closedHint') }
    ]);

    function createLink() {
      createInviteLink(group.value);
      toast(t('groupSettings.link.created'));
    }

    async function copyLink() {
      await copyText(linkUrl.value);
      toast(t('group.link.copied'));
    }

    function setLock(value) {
      group.value.inviteLink.open = value === 'open';
      toast(t(value === 'open' ? 'groupSettings.link.nowOpen' : 'groupSettings.link.nowClosed'));
    }

    function revokeLink() {
      group.value.inviteLink = null;
      toast(t('groupSettings.link.revoked'));
    }

    function regenerateLink() {
      const open = group.value.inviteLink.open;
      createInviteLink(group.value);
      group.value.inviteLink.open = open;
      toast(t('groupSettings.link.regenerated'));
    }

    function previewJoin() {
      go('join', { currentGroupId: group.value.id, joinToken: group.value.inviteLink ? group.value.inviteLink.token : null });
    }

    function setOption(checked) {
      group.value.membersCanSetStatus = checked;
      toast(t('groupSettings.options.saved'));
    }

    // new-group: the shared invitation component, same behavior as on the group page (F-04, F-05)
    const invites = ref([]);

    function invite() {
      const invited = inviteToGroup(group.value, invites.value, user.value.id);
      invites.value = [];

      if (!invited.length) {
        return;
      }

      toast(invitedToast(invited));
    }

    return {
      user, group, isOwner, canManage, members, counts, name, nameError, removing, deleting,
      saveName, setRole, remove, confirmDelete,
      linkUrl, lockOptions, createLink, copyLink, setLock, revokeLink, regenerateLink, previewJoin, setOption,
      invites, invite,
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

      <section class="section" aria-labelledby="gs-link-title" data-feature="F-04">
        <div class="section-head">
          <h2 id="gs-link-title">{{ t('groupSettings.link.title') }}</h2>
          <span class="notice">{{ t('groupSettings.link.hint') }}</span>
        </div>
        <div class="card gs-link" v-if="group.inviteLink">
          <FormField :label="t('groupSettings.link.url')" control="gs-link-url">
            <div class="gs-link__row">
              <input class="input" id="gs-link-url" :value="linkUrl" readonly>
              <button type="button" class="btn btn--secondary" @click="copyLink"><Icon name="link" /> {{ t('groupSettings.link.copy') }}</button>
            </div>
          </FormField>
          <FormField :label="t('groupSettings.link.lock')" group>
            <ChoiceGroup :modelValue="group.inviteLink.open ? 'open' : 'closed'" name="gs-link-lock" :options="lockOptions" @update:modelValue="setLock" />
          </FormField>
          <div class="gs-link__actions">
            <span class="gs-link__state"><Icon :name="group.inviteLink.open ? 'unlock' : 'lock'" class="icon-sm" /> {{ group.inviteLink.open ? t('groupSettings.link.nowOpen') : t('groupSettings.link.nowClosed') }}</span>
            <button type="button" class="btn btn--secondary btn--sm" @click="regenerateLink">{{ t('groupSettings.link.regenerate') }}</button>
            <button type="button" class="btn btn--danger btn--sm" @click="revokeLink">{{ t('groupSettings.link.revoke') }}</button>
            <button type="button" class="btn btn--ghost btn--sm" @click="previewJoin">{{ t('groupSettings.link.preview') }}</button>
          </div>
        </div>
        <div class="card gs-link" v-else>
          <p class="muted">{{ t('groupSettings.link.none') }}</p>
          <div class="gs-link__actions">
            <button type="button" class="btn btn--primary" @click="createLink"><Icon name="link" /> {{ t('groupSettings.link.create') }}</button>
            <button type="button" class="btn btn--ghost btn--sm" @click="previewJoin">{{ t('groupSettings.link.preview') }}</button>
          </div>
        </div>
      </section>

      <section class="section" aria-labelledby="gs-options-title" data-feature="F-03">
        <div class="section-head">
          <h2 id="gs-options-title">{{ t('groupSettings.options') }}</h2>
          <span class="notice" v-if="!isOwner">{{ t('groupSettings.options.ownerOnly') }}</span>
        </div>
        <div class="card gs-options">
          <label class="check" data-feature="F-11">
            <input type="checkbox" :checked="!!group.membersCanSetStatus" :disabled="!isOwner" @change="setOption($event.target.checked)">
            <span class="check__text">
              {{ t('groupSettings.options.setStatus') }}
              <span class="check__hint">{{ t('groupSettings.options.setStatusHint') }}</span>
            </span>
          </label>
        </div>
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
                  <span class="badge badge--info" v-if="membership.joinedViaLink" data-feature="F-04">{{ t('group.member.viaLink') }}</span>
                </span>
                <span class="gs-member__email" v-if="h.account(membership.accountId).email">{{ h.account(membership.accountId).email }}</span>
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

          <InviteField v-model="invites" :group-id="group.id" :label="t('group.invite.title')" :hint="t('group.invite.hint')" :submit-label="t('group.invite.button')" :link="false" id="gs-invite" @submit="invite" />
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

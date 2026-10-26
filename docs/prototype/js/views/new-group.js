// new-group: New group page (F-03, F-04): name plus the invitation component. Inviting can be skipped.

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, data, currentUser, go, toast, nextId } from '../core.js';
import { t } from '../i18n.js';
import { InviteField, inviteToGroup } from '../invite-field.js';

export const NewGroupView = {
  components: { InviteField },
  setup() {
    const user = currentUser;
    const name = ref('');
    const invites = ref([]);
    const error = ref('');
    const inviteLabel = computed(() => t('newGroup.invites'));

    function cancel() {
      go(ui.returnView || 'dashboard');
    }

    function create() {
      if (!name.value.trim()) {
        error.value = t('newGroup.nameRequired');

        return;
      }

      const group = { id: nextId('g'), name: name.value.trim(), ownerId: user.value.id, membersCanSetStatus: false, inviteLink: null };
      data.groups.push(group);
      data.memberships.push({ groupId: group.id, accountId: user.value.id, role: 'owner', acceptedAt: '2026-10-25' });
      const invited = inviteToGroup(group, invites.value, user.value.id);

      toast(invited.length ? t('newGroup.createdWith', { n: invited.length }) : t('newGroup.created'));
      go('group', { currentGroupId: group.id });
    }

    return { name, invites, error, inviteLabel, cancel, create, t };
  },
  template: `
    <FormPage :title="t('newGroup.title')" :subtitle="t('newGroup.sub')" @cancel="cancel">
      <form id="new-group-form" @submit.prevent="create" class="form-grid new-group" novalidate data-feature="F-03">
        <FormField :label="t('newGroup.name')" control="group-name" required :error="error">
          <input class="input" id="group-name" v-model="name" :placeholder="t('newGroup.namePlaceholder')" :aria-invalid="error ? 'true' : null" @input="error = ''">
        </FormField>
        <InviteField v-model="invites" :label="inviteLabel" :hint="t('newGroup.invitesHint')" id="group-invite" />
      </form>
      <template #actions>
        <button type="button" class="btn btn--secondary" @click="cancel">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-group-form" class="btn btn--primary">{{ t('newGroup.create') }}</button>
      </template>
    </FormPage>`
};

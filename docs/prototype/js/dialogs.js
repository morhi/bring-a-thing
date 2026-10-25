// Dialogs that stay dialogs: group creation (two fields) and the stub view for standard pages.

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, data, currentUser, closeDialog, toast, nextId, go } from './core.js';
import { t } from './i18n.js';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const NewGroupDialog = {
  setup() {
    const name = ref('');
    const invites = ref('');
    const error = ref('');

    function create() {
      if (!name.value.trim()) {
        error.value = t('newGroup.nameRequired');

        return;
      }

      const group = { id: nextId('g'), name: name.value.trim(), ownerId: currentUser.value.id };
      data.groups.push(group);
      data.memberships.push({ groupId: group.id, accountId: currentUser.value.id, role: 'owner', acceptedAt: '2026-10-25' });

      invites.value.split(/\n|,/).map((line) => line.trim().toLowerCase()).filter((line) => emailPattern.test(line)).forEach((email) => {
        let account = data.accounts.find((entry) => entry.email.toLowerCase() === email);

        if (!account) {
          account = { id: nextId('u'), name: '', email, language: ui.lang, tone: 5 };
          data.accounts.push(account);
        }

        data.memberships.push({ groupId: group.id, accountId: account.id, role: 'member', acceptedAt: null });
      });

      closeDialog();
      toast(t('newGroup.created'));
      go('group', { currentGroupId: group.id });
    }

    return { name, invites, error, create, closeDialog, t };
  },
  template: `
    <AppDialog :title="t('newGroup.title')" :subtitle="t('newGroup.sub')" @close="closeDialog">
      <form id="new-group-form" @submit.prevent="create" class="form-grid" novalidate>
        <FormField :label="t('newGroup.name')" control="group-name" required :error="error">
          <input class="input" id="group-name" v-model="name" :placeholder="t('newGroup.namePlaceholder')" :aria-invalid="error ? 'true' : null">
        </FormField>
        <FormField :label="t('newGroup.invites')" control="group-invites" :hint="t('newGroup.invitesHint')" data-feature="F-04">
          <textarea class="textarea" id="group-invites" v-model="invites" placeholder="lena.frei@gmail.com&#10;tobi.keller@bluewin.ch"></textarea>
        </FormField>
      </form>
      <template #foot>
        <button type="button" class="btn btn--secondary" @click="closeDialog">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-group-form" class="btn btn--primary">{{ t('newGroup.create') }}</button>
      </template>
    </AppDialog>`
};

export const StubView = {
  setup() {
    const kind = computed(() => ui.view);
    const isStandard = computed(() => ['settings', 'friends'].includes(ui.view));

    return { ui, kind, isStandard, go, t };
  },
  template: `
    <main class="container page">
      <div class="stub">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <span class="badge">{{ isStandard ? t('stub.standard') : t('stub.next.title') }}</span>
        <h1>{{ isStandard ? t('stub.' + kind + '.title') : kind.charAt(0).toUpperCase() + kind.slice(1) }}</h1>
        <p class="lede">{{ isStandard ? t('stub.' + kind + '.text') : t('stub.next.text') }}</p>
        <LangSwitch v-if="kind === 'settings'" />
      </div>
    </main>`
};

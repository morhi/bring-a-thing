// Join page (F-04, F-01): public page behind the group invite link. Open link: a name is enough and the
// person joins at once. Closed link: email login first. Revoked or unknown link: "Not found".
// Review-bar states override the real link state so every variant is reachable from any group.

import { ref, reactive, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, go, viewState, nextId } from '../core.js';
import { t } from '../i18n.js';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const JOIN_DATE = '2026-10-25';

export const JoinView = {
  setup() {
    const state = viewState('join');
    const group = computed(() => h.group(ui.currentGroupId));
    const link = computed(() => (group.value ? group.value.inviteLink : null));

    const found = computed(() => {
      if (!group.value || state.value === 'linkRevoked') {
        return false;
      }

      if (state.value !== 'default') {
        return true;
      }

      return !!link.value && (!ui.joinToken || link.value.token === ui.joinToken);
    });

    const open = computed(() => {
      if (state.value === 'linkOpen') {
        return true;
      }

      if (state.value === 'linkClosed') {
        return false;
      }

      return !!link.value && link.value.open;
    });

    const members = computed(() => (group.value ? h.membersOf(group.value.id) : []));
    const form = reactive({ name: '', email: '', nameError: '', emailError: '' });
    // Steps of the closed flow: form (email), sent, name (new account), joined. The open flow goes form to joined.
    const step = ref('form');
    const joinedViaName = ref(false);

    watch([state, () => ui.currentGroupId], () => {
      step.value = 'form';
      Object.assign(form, { name: '', email: '', nameError: '', emailError: '' });
    });

    function addMember(account, viaLink) {
      const membership = data.memberships.find((entry) => entry.groupId === group.value.id && entry.accountId === account.id);

      if (membership) {
        membership.acceptedAt = membership.acceptedAt || JOIN_DATE;

        return;
      }

      data.memberships.push({ groupId: group.value.id, accountId: account.id, role: 'member', acceptedAt: JOIN_DATE, joinedViaLink: viaLink });
    }

    function newAccount(name, email) {
      const account = { id: nextId('u'), name, email, language: ui.lang, tone: 1 + (data.accounts.length % 5) };
      data.accounts.push(account);

      return account;
    }

    function validName() {
      form.nameError = form.name.trim() ? '' : t('join.nameRequired');

      return !form.nameError;
    }

    function joinByName() {
      if (!validName()) {
        return;
      }

      addMember(newAccount(form.name.trim(), ''), true);
      joinedViaName.value = true;
      step.value = 'joined';
    }

    function sendLink() {
      const email = form.email.trim().toLowerCase();

      if (!emailPattern.test(email)) {
        form.emailError = t('welcome.login.invalid');

        return;
      }

      form.emailError = '';
      form.email = email;
      step.value = 'sent';
    }

    // Opening the login link: a named account joins directly, a new or nameless one is asked for its name first (F-01).
    function openLoginLink() {
      const account = data.accounts.find((entry) => entry.email.toLowerCase() === form.email);

      if (account && account.name) {
        addMember(account, false);
        joinedViaName.value = false;
        step.value = 'joined';

        return;
      }

      step.value = 'name';
    }

    function finishClosed() {
      if (!validName()) {
        return;
      }

      let account = data.accounts.find((entry) => entry.email.toLowerCase() === form.email);

      if (account) {
        account.name = form.name.trim();
      } else {
        account = newAccount(form.name.trim(), form.email);
      }

      addMember(account, false);
      joinedViaName.value = false;
      step.value = 'joined';
    }

    function submit() {
      if (open.value) {
        joinByName();

        return;
      }

      if (step.value === 'name') {
        finishClosed();

        return;
      }

      sendLink();
    }

    return { ui, state, group, found, open, members, form, step, joinedViaName, submit, openLoginLink, go, t };
  },
  template: `
    <main class="container page join-page">
      <div class="guest-bar" data-feature="F-04">
        <Wordmark :as-button="false" />
        <span class="guest-bar__spacer"></span>
        <LangSwitch />
      </div>

      <div class="join-body" v-if="!found">
        <div class="stack" data-feature="F-04">
          <h1>{{ t('group.notFound') }}</h1>
          <p class="lede">{{ t('join.notFoundText') }}</p>
        </div>
      </div>

      <div class="join-body" v-else-if="step === 'joined'">
        <div class="join-card join-card--done" role="status" data-feature="F-04">
          <span class="join-card__check"><Icon name="check" /></span>
          <h1>{{ t('join.joinedTitle', { group: group.name }) }}</h1>
          <p class="lede" :data-feature="joinedViaName ? 'F-01' : null">{{ joinedViaName ? t('join.joinedText.open') : t('join.joinedText.closed') }}</p>
          <button type="button" class="btn btn--primary" @click="go('group', { currentGroupId: group.id })"><Icon name="chevron" /> {{ t('join.openGroup') }}</button>
        </div>
      </div>

      <div class="join-body" v-else>
        <div class="join-intro" data-feature="F-04">
          <span class="badge badge--primary join-lock"><Icon :name="open ? 'unlock' : 'lock'" class="icon-sm" /> {{ open ? t('groupSettings.link.openTitle') : t('groupSettings.link.closedTitle') }}</span>
          <h1>{{ t('join.title', { group: group.name }) }}</h1>
          <p class="lede">{{ t(open ? 'join.lede.open' : 'join.lede.closed', { group: group.name }) }}</p>
          <div class="join-members">
            <AvatarStack :ids="members" :max="5" />
            <span>{{ members.length }} {{ t('word.members') }}</span>
          </div>
        </div>

        <form class="join-card" @submit.prevent="submit" novalidate data-feature="F-01">
          <template v-if="open || step === 'name'">
            <FormField :label="t('welcome.login.name')" control="join-name" required :error="form.nameError" :hint="t('join.nameHint')">
              <input class="input" id="join-name" type="text" autocomplete="name" v-model="form.name" :aria-invalid="form.nameError ? 'true' : null">
            </FormField>
            <button type="submit" class="btn btn--primary btn--block"><Icon name="check" /> {{ t('join.button') }}</button>
          </template>

          <template v-else-if="step === 'form'">
            <FormField :label="t('welcome.login.email')" control="join-email" required :error="form.emailError" :hint="t('welcome.login.note')">
              <input class="input" id="join-email" type="email" autocomplete="email" v-model="form.email" placeholder="name@example.com" :aria-invalid="form.emailError ? 'true' : null">
            </FormField>
            <button type="submit" class="btn btn--primary btn--block"><Icon name="mail" /> {{ t('welcome.login.sendLink') }}</button>
          </template>

          <template v-else>
            <div class="join-card__sent" role="status">
              <strong>{{ t('welcome.login.sentTitle') }}</strong>
              <span>{{ t('welcome.login.sentText', { email: form.email }) }}</span>
              <span>{{ t('join.sentNote', { group: group.name }) }}</span>
            </div>
            <div class="stack stack--tight">
              <button type="button" class="btn btn--primary btn--block" @click="openLoginLink"><Icon name="chevron" /> {{ t('welcome.login.sentDemo') }}</button>
              <button type="button" class="btn btn--ghost btn--block" @click="step = 'form'">{{ t('welcome.login.again') }}</button>
            </div>
          </template>
        </form>
      </div>
    </main>`
};

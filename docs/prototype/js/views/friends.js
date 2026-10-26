// Friends page (F-05): everyone you invited or added by email, add and remove.

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, viewState, toast, nextId } from '../core.js';
import { t } from '../i18n.js';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function nameOf(account) {
  return account.name || account.email;
}

export const FriendsView = {
  setup() {
    const state = viewState('friends');
    const user = currentUser;
    const email = ref('');
    const error = ref('');

    const friends = computed(() => {
      if (state.value === 'empty') {
        return [];
      }

      // Named friends first, then the shadow accounts that have not logged in yet.
      return h.friendsOf(user.value.id)
        .filter(Boolean)
        .sort((a, b) => (a.name ? 0 : 1) - (b.name ? 0 : 1) || nameOf(a).localeCompare(nameOf(b)));
    });

    function sharedGroupsLabel(friend) {
      const groups = h.groupsOf(user.value.id).filter((group) => h.membersOf(group.id).includes(friend.id));

      if (!groups.length) {
        return t('friends.noSharedGroups');
      }

      return t('friends.sharedGroups', { groups: groups.map((group) => group.name).join(', ') });
    }

    function add() {
      const value = email.value.trim().toLowerCase();

      if (!emailPattern.test(value)) {
        error.value = t('friends.add.invalid');

        return;
      }

      if (value === user.value.email.toLowerCase()) {
        error.value = t('friends.add.self');

        return;
      }

      let account = data.accounts.find((entry) => entry.email.toLowerCase() === value);

      if (account && data.friends.some((entry) => entry.accountId === user.value.id && entry.friendId === account.id)) {
        error.value = t('friends.add.exists', { name: nameOf(account) });

        return;
      }

      // A friend references an account, so an unknown address gets a shadow account without any email being sent.
      if (!account) {
        account = { id: nextId('u'), name: '', email: value, language: ui.lang, tone: 5 };
        data.accounts.push(account);
      }

      data.friends.push({ accountId: user.value.id, friendId: account.id });
      state.value = 'default';
      error.value = '';
      email.value = '';
      toast(t('friends.add.added', { name: nameOf(account) }));
    }

    function remove(friend) {
      data.friends = data.friends.filter((entry) => !(entry.accountId === user.value.id && entry.friendId === friend.id));
      toast(t('friends.removed', { name: nameOf(friend) }));
    }

    return { user, email, error, friends, sharedGroupsLabel, add, remove, nameOf, go, t };
  },
  template: `
    <main class="container page">
      <div class="friends-head" data-feature="F-05">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('friends.title') }}<span class="section-head__count" v-if="friends.length">{{ friends.length }}</span></h1>
        <p class="lede">{{ t('friends.lede') }}</p>
      </div>

      <div class="friends-body">
        <section class="section" aria-labelledby="friends-list-title" data-feature="F-05">
          <div class="section-head">
            <h2 id="friends-list-title">{{ t('friends.list') }}</h2>
          </div>
          <ul class="card friends-list rows" v-if="friends.length">
            <li class="friend" v-for="friend in friends" :key="friend.id">
              <Avatar :id="friend.id" />
              <span class="friend__main">
                <span class="friend__name">
                  <span v-if="friend.name">{{ friend.name }}</span>
                  <span class="soft" v-else>{{ t('common.noName') }}</span>
                  <span class="badge badge--warning" v-if="!friend.name">{{ t('friends.pending') }}</span>
                </span>
                <span class="friend__email">{{ friend.email }}</span>
                <span class="friend__groups">{{ sharedGroupsLabel(friend) }}</span>
              </span>
              <button type="button" class="btn btn--ghost btn--icon btn--sm friend__remove" :aria-label="t('friends.remove', { name: nameOf(friend) })" @click="remove(friend)"><Icon name="x" /></button>
            </li>
          </ul>
          <EmptyState v-else :title="t('friends.empty.title')" :text="t('friends.empty.text')" />
        </section>

        <aside class="friends-side" aria-labelledby="friends-add-title">
          <form class="card friend-add" @submit.prevent="add" novalidate data-feature="F-05">
            <h2 id="friends-add-title">{{ t('friends.add.title') }}</h2>
            <FormField :label="t('friends.add.email')" control="friend-email" :hint="t('friends.add.hint')" :error="error">
              <div class="friend-add__row">
                <input class="input" id="friend-email" type="email" v-model="email" :placeholder="t('friends.add.placeholder')" autocomplete="off" :aria-invalid="error ? 'true' : null">
                <button type="submit" class="btn btn--primary"><Icon name="plus" /> <span class="visually-hidden">{{ t('friends.add.button') }}</span></button>
              </div>
            </FormField>
          </form>
        </aside>
      </div>
    </main>`
};

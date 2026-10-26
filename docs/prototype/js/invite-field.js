// new-group: invitation component (F-04, F-05). One input takes a friend's name or an email address,
// selected people collect as chips, the parent applies them (create group, invite into an existing group).

import { ref, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, toast, nextId, createInviteLink, inviteUrl, copyText } from './core.js';
import { t } from './i18n.js';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const maxSuggestions = 4;

function accountByEmail(email) {
  return data.accounts.find((entry) => entry.email && entry.email.toLowerCase() === email);
}

export function selectionName(selection) {
  if (!selection.accountId) {
    return selection.email;
  }

  return h.displayName(selection.accountId) || selection.email;
}

// Adds the selected people to the group: known accounts are reused, unknown emails get a shadow account
// that stays "Invitation pending" until the first login, and everyone becomes a friend of the inviter (F-04, F-05).
export function inviteToGroup(group, selections, inviterId) {
  const invited = [];

  selections.forEach((selection) => {
    let account = selection.accountId ? h.account(selection.accountId) : accountByEmail(selection.email);

    if (!account) {
      account = { id: nextId('u'), name: '', email: selection.email, language: ui.lang, tone: 5 };
      data.accounts.push(account);
    }

    if (h.membersOf(group.id).includes(account.id)) {
      return;
    }

    data.memberships.push({ groupId: group.id, accountId: account.id, role: 'member', acceptedAt: account.name ? '2026-10-25' : null });

    if (!data.friends.some((entry) => entry.accountId === inviterId && entry.friendId === account.id)) {
      data.friends.push({ accountId: inviterId, friendId: account.id });
    }

    invited.push(account);
  });

  return invited;
}

export function invitedToast(invited) {
  if (invited.length === 1) {
    return t('invite.sentOne', { name: invited[0].name || invited[0].email });
  }

  return t('invite.sent', { n: invited.length });
}

export const InviteField = {
  props: {
    modelValue: { type: Array, required: true },
    groupId: { type: String, default: null },
    label: { type: String, required: true },
    hint: String,
    submitLabel: String,
    link: { type: Boolean, default: true },
    id: { type: String, default: 'invite' }
  },
  emits: ['update:modelValue', 'submit'],
  setup(props, { emit }) {
    const user = currentUser;
    const query = ref('');
    const open = ref(false);
    const active = ref(0);
    const group = computed(() => (props.groupId ? h.group(props.groupId) : null));
    const listId = `${props.id}-list`;

    // In create mode the only member so far is the person creating the group.
    const memberIds = computed(() => (group.value ? h.membersOf(group.value.id) : [user.value.id]));
    const selectedIds = computed(() => props.modelValue.map((selection) => selection.accountId).filter(Boolean));
    const selectedEmails = computed(() => props.modelValue.map((selection) => selection.email));

    const rows = computed(() => {
      const term = query.value.trim().toLowerCase();

      if (!term) {
        return [];
      }

      const friends = h.friendsOf(user.value.id)
        .filter((friend) => friend && friend.email && !selectedIds.value.includes(friend.id))
        .filter((friend) => friend.email.toLowerCase().includes(term) || (friend.name || '').toLowerCase().includes(term))
        .slice(0, maxSuggestions)
        .map((friend) => ({ kind: 'friend', account: friend, disabled: memberIds.value.includes(friend.id) }));

      const exactFriend = friends.some((row) => row.account.email.toLowerCase() === term);

      if (!emailPattern.test(term) || exactFriend || selectedEmails.value.includes(term)) {
        return friends;
      }

      // An email of someone who is a member but not a friend shows only the address, so nothing about them leaks.
      const known = accountByEmail(term);
      const disabled = !!known && memberIds.value.includes(known.id);

      return [{ kind: 'email', email: term, disabled }, ...friends];
    });

    const noMatch = computed(() => query.value.trim().length > 0 && rows.value.length === 0);
    const hintText = computed(() => (noMatch.value ? t('invite.noMatch') : props.hint));
    const expanded = computed(() => open.value && rows.value.length > 0);

    watch(rows, (value) => {
      const first = value.findIndex((row) => !row.disabled);
      active.value = first < 0 ? 0 : first;
    });

    function optionId(index) {
      return `${props.id}-option-${index}`;
    }

    function pick(row) {
      if (row.disabled) {
        return;
      }

      const selection = row.kind === 'friend'
        ? { accountId: row.account.id, email: row.account.email }
        : { accountId: null, email: row.email };

      emit('update:modelValue', [...props.modelValue, selection]);
      query.value = '';
      open.value = false;
    }

    function remove(selection) {
      emit('update:modelValue', props.modelValue.filter((entry) => entry !== selection));
    }

    function move(step) {
      if (!rows.value.length) {
        return;
      }

      open.value = true;
      active.value = (active.value + step + rows.value.length) % rows.value.length;
    }

    function onKey(event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        move(event.key === 'ArrowDown' ? 1 : -1);

        return;
      }

      if (event.key === 'Escape') {
        if (!open.value) {
          return;
        }

        event.preventDefault();
        open.value = false;

        return;
      }

      if (event.key === 'Backspace' && !query.value && props.modelValue.length) {
        remove(props.modelValue[props.modelValue.length - 1]);

        return;
      }

      if (event.key !== 'Enter' && event.key !== ',') {
        return;
      }

      // Enter never submits the surrounding form from this input; it adds the active suggestion instead.
      event.preventDefault();
      const row = rows.value[active.value];

      if (!row) {
        return;
      }

      pick(row);
    }

    function onInput() {
      open.value = true;
    }

    function onBlur() {
      open.value = false;
    }

    // The button creates the link on first use, then copies it (F-04). Without a group yet it stays disabled.
    async function copyLink() {
      if (!group.value) {
        return;
      }

      const created = !group.value.inviteLink;

      if (created) {
        createInviteLink(group.value);
      }

      await copyText(inviteUrl(group.value));
      toast(t(created ? 'group.link.created' : 'group.link.copied'));
    }

    return { query, open, active, group, listId, rows, noMatch, hintText, expanded, optionId, pick, remove, onKey, onInput, onBlur, copyLink, selectionName, t };
  },
  template: `
    <div class="invite-field" data-feature="F-04">
      <FormField :label="label" :control="id" :hint="hintText">
        <div class="invite-field__box">
          <input class="input" :id="id" v-model="query" :placeholder="t('invite.placeholder')" autocomplete="off" spellcheck="false"
            role="combobox" aria-autocomplete="list" aria-haspopup="listbox" :aria-expanded="expanded" :aria-controls="listId"
            :aria-activedescendant="expanded ? optionId(active) : null"
            @input="onInput" @focus="onInput" @blur="onBlur" @keydown="onKey">
          <ul class="suggestions" :id="listId" role="listbox" :aria-label="t('invite.suggestions')" v-if="expanded" data-feature="F-05">
            <li v-for="(row, index) in rows" :key="row.kind === 'friend' ? row.account.id : row.email" :id="optionId(index)" role="option"
              :class="['suggestion', index === active ? 'suggestion--active' : '', row.disabled ? 'suggestion--disabled' : '']"
              :aria-selected="index === active" :aria-disabled="row.disabled || null" @mousedown.prevent @click="pick(row)" @mousemove="active = index">
              <template v-if="row.kind === 'friend'">
                <Avatar :id="row.account.id" size="sm" />
                <span class="suggestion__main">
                  <span class="suggestion__name">{{ row.account.name || t('common.noName') }}</span>
                  <span class="suggestion__email">{{ row.account.email }}</span>
                </span>
              </template>
              <template v-else>
                <span class="suggestion__icon"><Icon name="mail" class="icon-md" /></span>
                <span class="suggestion__main"><span class="suggestion__name">{{ t('invite.addEmail', { email: row.email }) }}</span></span>
              </template>
              <span class="badge" v-if="row.disabled">{{ t('invite.alreadyMember') }}</span>
            </li>
          </ul>
        </div>
      </FormField>
      <ul class="invite-chips" v-if="modelValue.length" :aria-label="t('invite.selected')">
        <li class="invite-chip" v-for="selection in modelValue" :key="selection.accountId || selection.email">
          <Avatar v-if="selection.accountId" :id="selection.accountId" size="sm" />
          <Icon v-else name="mail" class="icon-sm invite-chip__icon" />
          <span class="invite-chip__name">{{ selectionName(selection) }}</span>
          <button type="button" class="invite-chip__remove" :aria-label="t('invite.remove', { name: selectionName(selection) })" @click="remove(selection)"><Icon name="x" /></button>
        </li>
      </ul>
      <div class="invite-field__actions">
        <button type="button" class="btn btn--primary" v-if="submitLabel" :disabled="!modelValue.length" @click="$emit('submit')"><Icon name="plus" /> {{ submitLabel }}</button>
        <button type="button" class="btn btn--secondary" v-if="link" :disabled="!group" @click="copyLink"><Icon name="link" /> {{ t('group.link.copy') }}</button>
      </div>
      <p class="field__hint" v-if="link && !group">{{ t('invite.linkLater') }}</p>
    </div>`
};

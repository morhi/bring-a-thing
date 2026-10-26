// List page (F-13 to F-20): items with claims, comments, attendance day and the share-link guest view.
// Editing happens on the list edit page (views/create.js), which shares the helpers exported here.

import { ref, reactive, computed, nextTick, watch, onBeforeUnmount } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, goCreate, viewState, toast, nextId } from '../core.js';
import { t, formatDate, relativeDay, isPast, TODAY } from '../i18n.js';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function listGroupId(list) {
  if (list.groupId) {
    return list.groupId;
  }

  if (list.eventId) {
    return h.event(list.eventId).groupId;
  }

  return null;
}

function formatQuantity(quantity, unit) {
  const number = quantity.toLocaleString(ui.lang === 'de' ? 'de-CH' : 'en-GB');

  return unit ? `${number} ${unit}` : number;
}

function nameOf(accountId) {
  return h.displayName(accountId) || h.account(accountId).email;
}

function pollOption(id) {
  return data.pollOptions.find((option) => option.id === id) || null;
}

function answerFor(optionId, accountId) {
  const response = data.responses.find((entry) => entry.optionId === optionId && entry.accountId === accountId);

  return response ? response.answer : null;
}

export function optionLabel(option) {
  const date = formatDate(option.date, 'weekday');

  if (!option.start) {
    return date;
  }

  return `${date}, ${option.start} - ${option.end}${option.label ? ' ' + option.label : ''}`;
}

// Only attendance polls of the same group can be linked (F-18).
export function attendancePollsOf(groupId) {
  return h.pollsOf(groupId)
    .filter((poll) => poll.type === 'attendance')
    .map((poll) => ({ poll, options: h.options(poll.id) }));
}

function now() {
  const time = new Date();
  const pad = (value) => String(value).padStart(2, '0');

  return `${TODAY.toISOString().slice(0, 10)}T${pad(time.getHours())}:${pad(time.getMinutes())}`;
}

const ConfirmDialog = {
  props: { title: { type: String, required: true }, text: String, confirmLabel: { type: String, required: true }, danger: Boolean },
  emits: ['confirm', 'cancel'],
  setup() {
    return { t };
  },
  template: `
    <AppDialog :title="title" @close="$emit('cancel')">
      <p v-if="text">{{ text }}</p>
      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('cancel')">{{ t('dialog.cancel') }}</button>
        <button type="button" :class="['btn', danger ? 'btn--danger' : 'btn--primary']" @click="$emit('confirm')">{{ confirmLabel }}</button>
      </template>
    </AppDialog>`
};

// Comment thread for a list or one item (F-20). Cmd/Ctrl+Enter sends.
const CommentThread = {
  props: { listId: { type: String, required: true }, itemId: String, canPost: Boolean, canModerate: Boolean, compact: Boolean },
  setup(props) {
    const user = currentUser;
    const text = ref('');
    const controlId = `comment-${props.itemId || props.listId}`;
    const modifierKey = /Mac|iPhone|iPad/.test(navigator.platform) ? 'Cmd' : 'Ctrl';
    const comments = computed(() => data.comments
      .filter((comment) => (props.itemId ? comment.itemId === props.itemId : comment.listId === props.listId && !comment.itemId))
      .sort((a, b) => a.at.localeCompare(b.at)));

    function send() {
      const body = text.value.trim();

      if (!body) {
        return;
      }

      data.comments.push({ id: nextId('c'), listId: props.listId, itemId: props.itemId || null, authorId: user.value.id, text: body, at: now() });
      text.value = '';
    }

    function onKeydown(event) {
      if (event.key !== 'Enter' || !(event.metaKey || event.ctrlKey)) {
        return;
      }

      event.preventDefault();
      send();
    }

    function canDelete(comment) {
      return props.canModerate || comment.authorId === user.value.id;
    }

    function remove(comment) {
      data.comments.splice(data.comments.indexOf(comment), 1);
    }

    function when(comment) {
      return `${formatDate(comment.at.slice(0, 10))}, ${comment.at.slice(11, 16)}`;
    }

    return { user, text, controlId, modifierKey, comments, send, onKeydown, canDelete, remove, when, h, t };
  },
  template: `
    <div :class="['thread', compact ? 'thread--compact' : '']">
      <ul class="thread__list" v-if="comments.length">
        <li class="comment" v-for="comment in comments" :key="comment.id">
          <Avatar :id="comment.authorId" size="sm" />
          <div class="comment__body">
            <div class="comment__meta">
              <span class="comment__author">{{ h.displayName(comment.authorId) || t('common.noName') }}</span>
              <span>{{ when(comment) }}</span>
              <button type="button" class="btn btn--ghost btn--icon btn--sm comment__delete" v-if="canPost && canDelete(comment)" :aria-label="t('list.comments.delete')" @click="remove(comment)"><Icon name="x" /></button>
            </div>
            <p class="comment__text">{{ comment.text }}</p>
          </div>
        </li>
      </ul>
      <EmptyState v-else :title="t('list.comments.none')" />
      <form class="composer" v-if="canPost" @submit.prevent="send">
        <FormField :label="t('list.comments.write')" :control="controlId" :hint="t('list.comments.hint', { key: modifierKey })">
          <textarea class="textarea composer__input" :id="controlId" v-model="text" rows="2" :placeholder="t('list.comments.placeholder')" @keydown="onKeydown"></textarea>
        </FormField>
        <button type="submit" class="btn btn--secondary btn--sm" :disabled="!text.trim()">{{ t('list.comments.send') }}</button>
      </form>
    </div>`
};

// Overflow menu of one item: claim for someone, comments, edit, delete. Closes on Escape, outside click and Tab; focus returns to the trigger.
const ItemMenu = {
  props: {
    itemId: { type: String, required: true },
    candidates: { type: Array, default: () => [] },
    canComment: Boolean,
    canEdit: Boolean
  },
  emits: ['claim-for', 'comment', 'edit', 'delete'],
  setup(props, { emit }) {
    const open = ref(false);
    const choosing = ref(false);
    const root = ref(null);
    const trigger = ref(null);
    const panel = ref(null);

    function items() {
      return panel.value ? Array.from(panel.value.querySelectorAll('[role="menuitem"]')) : [];
    }

    function close(returnFocus = true) {
      open.value = false;
      choosing.value = false;
      document.removeEventListener('pointerdown', onOutside);

      if (returnFocus && trigger.value) {
        trigger.value.focus();
      }
    }

    function onOutside(event) {
      if (root.value && !root.value.contains(event.target)) {
        close(false);
      }
    }

    function toggle() {
      if (open.value) {
        close();

        return;
      }

      open.value = true;
      document.addEventListener('pointerdown', onOutside);
      nextTick(() => {
        const first = items()[0];

        if (first) {
          first.focus();
        }
      });
    }

    function onKeydown(event) {
      if (!open.value) {
        return;
      }

      if (event.key === 'Escape') {
        event.stopPropagation();
        close();

        return;
      }

      if (event.key === 'Tab') {
        close(false);

        return;
      }

      if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
        return;
      }

      event.preventDefault();
      const list = items();
      const index = list.indexOf(document.activeElement);
      const target = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: list.length - 1 }[event.key];

      if (list.length) {
        list[(target + list.length) % list.length].focus();
      }
    }

    function pick(name, payload) {
      close();
      emit(name, payload);
    }

    function toggleChoosing() {
      choosing.value = !choosing.value;
    }

    onBeforeUnmount(() => document.removeEventListener('pointerdown', onOutside));

    return { open, choosing, root, trigger, panel, toggle, onKeydown, pick, toggleChoosing, nameOf, t };
  },
  template: `
    <div class="item-menu" ref="root" @keydown="onKeydown">
      <button type="button" class="btn btn--ghost btn--icon btn--sm" ref="trigger" aria-haspopup="menu" :aria-expanded="open" :aria-controls="'menu-' + itemId" :aria-label="t('list.item.more')" @click="toggle"><Icon name="dots" /></button>
      <div class="item-menu__panel" role="menu" :id="'menu-' + itemId" ref="panel" v-if="open">
        <button type="button" class="item-menu__item" role="menuitem" v-if="candidates.length" :aria-expanded="choosing" data-feature="F-15" @click="toggleChoosing">{{ t('list.claim.for') }}<Icon name="chevron" :class="['icon-sm', 'item-menu__chevron', choosing ? 'item-menu__chevron--open' : '']" /></button>
        <template v-if="choosing">
          <button type="button" class="item-menu__item item-menu__item--sub" role="menuitem" v-for="id in candidates" :key="id" @click="pick('claim-for', id)">{{ nameOf(id) }}</button>
        </template>
        <button type="button" class="item-menu__item" role="menuitem" v-if="canComment" data-feature="F-20" @click="pick('comment')">{{ t('list.comments.add') }}</button>
        <button type="button" class="item-menu__item" role="menuitem" v-if="canEdit" @click="pick('edit')">{{ t('list.item.edit') }}</button>
        <button type="button" class="item-menu__item item-menu__item--danger" role="menuitem" v-if="canEdit" @click="pick('delete')">{{ t('list.item.delete') }}</button>
      </div>
    </div>`
};

// Add or edit one item (F-14) with custom field values (F-16) and the attendance day override (F-18).
const ItemDialog = {
  props: { list: { type: Object, required: true }, item: Object, canDelete: Boolean },
  emits: ['close', 'delete'],
  setup(props, { emit }) {
    const fields = computed(() => data.customFields.filter((field) => field.listId === props.list.id));
    const attendancePolls = computed(() => (props.list.groupId ? attendancePollsOf(props.list.groupId) : []));
    const source = props.item || {};
    const form = reactive({
      name: source.name || '',
      quantity: source.quantity ?? '',
      unit: source.unit || '',
      notes: source.notes || '',
      date: source.date || '',
      attendanceOptionId: source.attendanceOptionId || '',
      fieldValues: Object.fromEntries(fields.value.map((field) => [field.id, (source.fieldValues || {})[field.id] || '']))
    });
    const errors = reactive({ name: '', quantity: '' });

    function save() {
      const quantity = form.quantity === '' || form.quantity === null ? null : Number(form.quantity);
      errors.name = form.name.trim() ? '' : t('list.item.nameRequired');
      errors.quantity = quantity === null || quantity > 0 ? '' : t('list.item.quantityInvalid');

      if (errors.name || errors.quantity) {
        return;
      }

      const values = {
        name: form.name.trim(),
        quantity,
        unit: quantity === null ? '' : form.unit.trim(),
        notes: form.notes.trim(),
        date: form.date || null,
        attendanceOptionId: form.attendanceOptionId || null,
        fieldValues: { ...form.fieldValues }
      };

      if (props.item) {
        Object.assign(props.item, values);
        toast(t('list.item.saved'));
        emit('close');

        return;
      }

      data.items.push({ id: nextId('i'), listId: props.list.id, ...values });
      toast(t('list.item.added'));
      emit('close');
    }

    return { fields, attendancePolls, form, errors, save, optionLabel, t };
  },
  template: `
    <AppDialog :title="item ? t('list.item.editTitle') : t('list.item.addTitle')" @close="$emit('close')">
      <form id="item-form" class="form-grid" @submit.prevent="save" novalidate data-feature="F-14">
        <FormField :label="t('list.item.name')" control="item-name" required :error="errors.name">
          <input class="input" id="item-name" v-model="form.name" :placeholder="t('list.item.namePlaceholder')" :aria-invalid="errors.name ? 'true' : null">
        </FormField>
        <div class="form-grid form-grid--2">
          <FormField :label="t('list.item.quantity')" control="item-quantity" :hint="t('list.item.quantityHint')" :error="errors.quantity">
            <input class="input" id="item-quantity" type="number" min="0" step="any" inputmode="decimal" v-model="form.quantity" :aria-invalid="errors.quantity ? 'true' : null">
          </FormField>
          <FormField :label="t('list.item.unit')" control="item-unit">
            <input class="input" id="item-unit" v-model="form.unit" :placeholder="t('list.item.unitPlaceholder')" :disabled="form.quantity === ''">
          </FormField>
        </div>
        <FormField :label="t('list.item.notes')" control="item-notes">
          <textarea class="textarea" id="item-notes" v-model="form.notes" rows="2"></textarea>
        </FormField>
        <FormField :label="t('list.item.date')" control="item-date" :hint="t('list.item.dateHint')">
          <input class="input input--date" id="item-date" type="date" v-model="form.date">
        </FormField>
        <FormField v-for="field in fields" :key="field.id" :label="field.name" :control="'item-field-' + field.id" data-feature="F-16">
          <input class="input" :id="'item-field-' + field.id" v-model="form.fieldValues[field.id]">
        </FormField>
        <FormField v-if="attendancePolls.length" :label="t('list.attendance.itemLabel')" control="item-attendance" :hint="t('list.attendance.itemHint')" data-feature="F-18">
          <select class="select" id="item-attendance" v-model="form.attendanceOptionId">
            <option value="">{{ t('list.attendance.sameAsList') }}</option>
            <optgroup v-for="entry in attendancePolls" :key="entry.poll.id" :label="entry.poll.title">
              <option v-for="option in entry.options" :key="option.id" :value="option.id">{{ optionLabel(option) }}</option>
            </optgroup>
          </select>
        </FormField>
      </form>
      <template #foot>
        <button type="button" class="btn btn--ghost" v-if="item && canDelete" @click="$emit('delete', item)">{{ t('list.item.delete') }}</button>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="item-form" class="btn btn--primary">{{ item ? t('list.item.save') : t('list.item.add') }}</button>
      </template>
    </AppDialog>`
};

export const ListView = {
  components: { ConfirmDialog, CommentThread, ItemDialog, ItemMenu },
  setup() {
    const state = viewState('list');
    const user = currentUser;
    const list = computed(() => h.list(ui.currentListId));
    const guest = computed(() => state.value === 'guest');
    const linkInvalid = computed(() => state.value === 'linkInvalid');
    // Granted by the in-place login: whoever came through the share link keeps seeing the list.
    const viaLink = ref(false);

    const group = computed(() => {
      const id = list.value ? listGroupId(list.value) : null;

      return id ? h.group(id) : null;
    });
    const event = computed(() => (list.value && list.value.eventId ? h.event(list.value.eventId) : null));
    const participation = computed(() => (event.value ? h.participation(event.value.id, user.value.id) : null));

    const canSee = computed(() => {
      if (!list.value) {
        return false;
      }

      if (event.value) {
        return ['accepted', 'pending'].includes(participation.value);
      }

      if (list.value.groupId) {
        return h.membersOf(list.value.groupId).includes(user.value.id);
      }

      if (list.value.ownerId === user.value.id || viaLink.value) {
        return true;
      }

      return h.items(list.value.id).some((item) => h.claimsOf(item.id).some((claim) => claim.accountId === user.value.id));
    });

    const isOwner = computed(() => !guest.value && list.value.ownerId === user.value.id);
    const isManager = computed(() => !guest.value && !!group.value && h.canManage(group.value.id, user.value.id));
    const canEdit = computed(() => isOwner.value || isManager.value);
    const canAdd = computed(() => canEdit.value || list.value.membersCanAddItems);
    const isPending = computed(() => !!event.value && participation.value === 'pending');
    // Guests see the claim controls too; they lead to the login (F-19).
    const canClaim = computed(() => !isPending.value);

    const listDate = computed(() => h.listDate(list.value));
    const past = computed(() => !!listDate.value && isPast(listDate.value));
    const items = computed(() => (state.value === 'empty' ? [] : h.items(list.value.id)));
    const fields = computed(() => data.customFields.filter((field) => field.listId === list.value.id));

    function remainingOf(item, claims = claimsOf(item)) {
      if (item.quantity === null) {
        return claims.length ? 0 : 1;
      }

      return Math.max(0, item.quantity - claims.reduce((sum, claim) => sum + (claim.quantity || 0), 0));
    }

    // The "all taken" review state fills every open item with a claim by another person.
    function claimsOf(item) {
      const claims = h.claimsOf(item.id);

      if (state.value !== 'allTaken') {
        return claims;
      }

      const remaining = remainingOf(item, claims);

      if (remaining <= 0) {
        return claims;
      }

      const filler = candidatesFor(item).find((id) => id !== user.value.id) || 'u4';

      return [...claims, { itemId: item.id, accountId: filler, quantity: item.quantity === null ? null : remaining, synthetic: true }];
    }

    function ownClaim(item) {
      if (guest.value) {
        return null;
      }

      return h.claimsOf(item.id).find((claim) => claim.accountId === user.value.id) || null;
    }

    const progress = computed(() => ({ done: items.value.filter((item) => remainingOf(item) === 0).length, total: items.value.length }));
    const allTaken = computed(() => items.value.length > 0 && progress.value.done === progress.value.total);

    const grouped = computed(() => items.value.some((item) => item.date));
    const groups = computed(() => {
      if (!grouped.value) {
        return [{ key: 'all', label: '', items: items.value }];
      }

      const byDate = new Map();
      items.value.forEach((item) => {
        const key = item.date || listDate.value || '';

        if (!byDate.has(key)) {
          byDate.set(key, []);
        }

        byDate.get(key).push(item);
      });

      return [...byDate.entries()]
        .sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : a.localeCompare(b)))
        .map(([key, entries]) => ({ key, label: key ? formatDate(key, 'weekday') : t('list.items.noDate'), items: entries }));
    });

    function statusLabel(item) {
      const remaining = remainingOf(item);

      if (item.quantity === null) {
        return remaining ? t('list.item.open') : t('list.item.taken');
      }

      return remaining ? t('list.item.missing', { n: formatQuantity(remaining, item.unit) }) : t('list.item.complete');
    }

    function fieldEntries(item) {
      return fields.value
        .map((field) => ({ id: field.id, name: field.name, value: (item.fieldValues || {})[field.id] }))
        .filter((entry) => entry.value);
    }

    // Attendance day (F-18): the item's option overrides the list's.
    const listOption = computed(() => (list.value.attendanceOptionId ? pollOption(list.value.attendanceOptionId) : null));

    function optionFor(item) {
      return item.attendanceOptionId ? pollOption(item.attendanceOptionId) : listOption.value;
    }

    function attendanceWarning(item, accountId) {
      const option = optionFor(item);

      if (!option) {
        return null;
      }

      const answer = answerFor(option.id, accountId);

      if (answer === 'yes') {
        return null;
      }

      return { option, answer };
    }

    function warningText(warning, accountId) {
      const date = formatDate(warning.option.date);

      if (accountId === user.value.id) {
        return warning.answer === 'no' ? t('list.attendance.youNo', { date }) : t('list.attendance.youNone', { date });
      }

      const name = nameOf(accountId);

      return warning.answer === 'no' ? t('list.attendance.otherNo', { name, date }) : t('list.attendance.otherNone', { name, date });
    }

    function warnFor(item) {
      if (guest.value || !canClaim.value || ownClaim(item) || remainingOf(item) === 0) {
        return '';
      }

      const warning = attendanceWarning(item, user.value.id);

      return warning ? warningText(warning, user.value.id) : '';
    }

    // Claim for others (F-15): only people who can see the list and have no claim on the item yet.
    function candidatesFor(item) {
      let ids;

      if (event.value) {
        ids = h.participants(event.value.id, 'accepted').map((entry) => entry.accountId);
      } else if (list.value.groupId) {
        ids = h.membersOf(list.value.groupId);
      } else {
        ids = [list.value.ownerId, ...h.items(list.value.id).flatMap((entry) => h.claimsOf(entry.id).map((claim) => claim.accountId))];
      }

      const taken = h.claimsOf(item.id).map((claim) => claim.accountId);

      return [...new Set(ids)].filter((id) => id !== user.value.id && !taken.includes(id));
    }

    const claimForm = reactive({ itemId: null, accountId: null, quantity: '', error: '' });
    const confirm = ref(null);

    function startClaim(item, accountId = user.value.id) {
      const own = h.claimsOf(item.id).find((claim) => claim.accountId === accountId);
      claimForm.itemId = item.id;
      claimForm.accountId = accountId;
      claimForm.quantity = own ? own.quantity : remainingOf(item);
      claimForm.error = '';
      nextTick(() => {
        const input = document.getElementById(`claim-qty-${item.id}`);

        if (input) {
          input.focus();
          input.select();
        }
      });
    }

    function cancelClaim() {
      claimForm.itemId = null;
      claimForm.error = '';
    }

    function submitClaim(item) {
      requestClaim(item, claimForm.accountId, claimForm.quantity);
    }

    function requestClaim(item, accountId, quantity) {
      let amount = null;

      if (item.quantity !== null) {
        amount = Number(quantity);

        if (!(amount > 0)) {
          claimForm.error = t('list.claim.amountRequired');

          return;
        }

        const own = h.claimsOf(item.id).find((claim) => claim.accountId === accountId);
        const available = remainingOf(item, h.claimsOf(item.id)) + (own ? own.quantity : 0);

        if (amount > available) {
          claimForm.error = t('list.claim.tooMuch', { n: formatQuantity(available, item.unit) });

          return;
        }
      }

      if (guest.value) {
        openLogin({ type: 'claim', itemId: item.id, quantity: amount });

        return;
      }

      const warning = attendanceWarning(item, accountId);

      if (!warning) {
        applyClaim(item, accountId, amount);

        return;
      }

      confirm.value = {
        title: t('list.attendance.confirmTitle'),
        text: warningText(warning, accountId),
        confirmLabel: t('list.attendance.claimAnyway'),
        action: () => applyClaim(item, accountId, amount)
      };
    }

    function applyClaim(item, accountId, amount) {
      const own = h.claimsOf(item.id).find((claim) => claim.accountId === accountId);

      if (own) {
        own.quantity = amount;
      } else {
        data.claims.push({ itemId: item.id, accountId, quantity: amount });
      }

      cancelClaim();
      toast(accountId === user.value.id ? t('list.claim.saved') : t('list.claim.savedFor', { name: nameOf(accountId) }));
    }

    function removeClaim(claim) {
      data.claims.splice(data.claims.indexOf(claim), 1);
      toast(t('list.claim.removed'));
    }

    function canRemoveClaim(claim) {
      return !guest.value && !claim.synthetic && (claim.accountId === user.value.id || canEdit.value);
    }

    function claimForId(item, accountId) {
      if (item.quantity === null) {
        requestClaim(item, accountId, null);

        return;
      }

      startClaim(item, accountId);
    }

    function runConfirm() {
      const action = confirm.value.action;
      confirm.value = null;
      action();
    }

    // Comments (F-20)
    const openThreads = reactive(new Set());

    function toggleThread(item) {
      if (openThreads.has(item.id)) {
        openThreads.delete(item.id);

        return;
      }

      openThreads.add(item.id);
    }

    function openComment(item) {
      if (openThreads.has(item.id)) {
        openThreads.delete(item.id);

        return;
      }

      openThreads.add(item.id);
      nextTick(() => {
        const input = document.getElementById(`comment-${item.id}`);

        if (input) {
          input.focus();
        }
      });
    }

    function commentCount(item) {
      return data.comments.filter((comment) => comment.itemId === item.id).length;
    }

    const listCommentCount = computed(() => data.comments.filter((comment) => comment.listId === list.value.id && !comment.itemId).length);

    // Item dialog
    const itemDialog = ref(null);

    function openItemDialog(item = null) {
      if (guest.value) {
        openLogin({ type: 'add' });

        return;
      }

      itemDialog.value = { item };
    }

    function askDeleteItem(item) {
      itemDialog.value = null;
      confirm.value = {
        title: t('list.item.deleteTitle'),
        text: t('list.item.deleteText', { name: item.name }),
        confirmLabel: t('list.item.delete'),
        danger: true,
        action: () => deleteItem(item)
      };
    }

    function deleteItem(item) {
      data.claims = data.claims.filter((claim) => claim.itemId !== item.id);
      data.comments = data.comments.filter((comment) => comment.itemId !== item.id);
      data.items.splice(data.items.indexOf(item), 1);
      toast(t('list.item.deleted'));
    }

    const backTarget = computed(() => {
      if (event.value) {
        return { view: 'event', ids: { currentEventId: event.value.id }, label: t('list.back.event', { event: event.value.title }) };
      }

      if (group.value) {
        return { view: 'group', ids: { currentGroupId: group.value.id }, label: t('list.back.group', { group: group.value.name }) };
      }

      return { view: 'dashboard', ids: {}, label: t('nav.backToDashboard') };
    });

    function back() {
      go(backTarget.value.view, backTarget.value.ids);
    }

    // edit-mode: the list edit page returns here on save and cancel (section 6, "List edit")
    function edit() {
      goCreate('new-list', { editListId: list.value.id });
    }

    // In-place login for guests (F-19, F-01). The pending action completes after the login.
    const login = reactive({ open: false, email: '', error: '', sent: false, pending: null });

    function openLogin(action = null) {
      login.pending = action;
      login.open = true;
      login.sent = false;
      nextTick(() => {
        const input = document.getElementById('guest-email');

        if (!input) {
          return;
        }

        input.scrollIntoView({ block: 'center', behavior: 'smooth' });
        input.focus({ preventScroll: true });
      });
    }

    function sendLink() {
      if (!emailPattern.test(login.email.trim())) {
        login.error = t('welcome.login.invalid');

        return;
      }

      login.error = '';
      login.sent = true;
    }

    function completeLogin() {
      const action = login.pending;
      login.open = false;
      login.pending = null;
      viaLink.value = true;
      state.value = 'default';

      if (!action) {
        return;
      }

      if (action.type === 'add') {
        itemDialog.value = { item: null };

        return;
      }

      const item = data.items.find((entry) => entry.id === action.itemId);

      if (!item) {
        return;
      }

      requestClaim(item, user.value.id, action.quantity);
    }

    const pendingLabel = computed(() => {
      if (!login.pending) {
        return '';
      }

      if (login.pending.type === 'add') {
        return t('list.guest.pendingAdd');
      }

      const item = data.items.find((entry) => entry.id === login.pending.itemId);

      return item ? t('list.guest.pendingClaim', { item: item.name }) : '';
    });

    watch(() => ui.currentListId, () => {
      cancelClaim();
      openThreads.clear();
      login.open = false;
    });

    return {
      ui, state, user, list, guest, linkInvalid, group, event, canSee, isOwner, canEdit, canAdd, isPending, canClaim,
      listDate, past, items, progress, allTaken, grouped, groups, listOption,
      claimsOf, ownClaim, remainingOf, statusLabel, fieldEntries, optionFor, warnFor, candidatesFor,
      claimForm, startClaim, cancelClaim, submitClaim, requestClaim, removeClaim, canRemoveClaim, claimForId, openComment,
      confirm, runConfirm, openThreads, toggleThread, commentCount, listCommentCount,
      itemDialog, openItemDialog, askDeleteItem, backTarget, back, edit,
      login, pendingLabel, openLogin, sendLink, completeLogin,
      go, h, t, formatDate, relativeDay, formatQuantity, nameOf
    };
  },
  template: `
    <main class="container page list-page" v-if="linkInvalid">
      <div class="guest-bar" data-feature="F-19">
        <Wordmark :as-button="false" />
        <span class="guest-bar__spacer"></span>
        <LangSwitch />
      </div>
      <div class="stack">
        <h1>{{ t('list.share.invalidTitle') }}</h1>
        <p class="lede">{{ t('list.share.invalidText') }}</p>
      </div>
    </main>

    <main class="container page list-page" v-else-if="list && (guest || canSee)">
      <div class="list-head" data-feature="F-13">
        <div class="guest-bar" v-if="guest" data-feature="F-19">
          <Wordmark :as-button="false" />
          <span class="guest-bar__spacer"></span>
          <LangSwitch />
          <button type="button" class="btn btn--secondary btn--sm" @click="openLogin()">{{ t('list.guest.login') }}</button>
        </div>
        <button type="button" class="back" v-else @click="back"><Icon name="back" /> {{ backTarget.label }}</button>

        <div class="list-head__top">
          <div class="list-head__title">
            <h1>{{ list.title }}</h1>
            <p class="lede" v-if="list.description">{{ list.description }}</p>
            <div class="list-head__meta">
              <span class="meta-chip" v-if="listDate">
                <Icon name="calendar" class="icon-sm" /> {{ formatDate(listDate, 'weekday') }}
                <span class="badge" v-if="past">{{ t('list.past') }}</span>
                <span class="badge badge--primary" v-else>{{ relativeDay(listDate) }}</span>
              </span>
              <template v-if="!guest">
                <button type="button" class="meta-chip meta-chip--link" v-if="event" @click="go('event', { currentEventId: event.id })"><Icon name="calendar" class="icon-sm" /> {{ t('list.placement.event', { event: event.title }) }}</button>
                <button type="button" class="meta-chip meta-chip--link" v-else-if="group" @click="go('group', { currentGroupId: group.id })"><Icon name="users" class="icon-sm" /> {{ t('list.placement.group', { group: group.name }) }}</button>
                <span class="meta-chip" v-else><Icon name="list" class="icon-sm" /> {{ t('list.placement.standalone') }}</span>
                <button type="button" class="meta-chip meta-chip--link" v-if="listOption" data-feature="F-18" @click="go('poll', { currentPollId: listOption.pollId })"><Icon name="grid" class="icon-sm" /> {{ t('list.attendance.day', { date: formatDate(listOption.date) }) }}</button>
                <span class="meta-chip" v-if="list.shareToken && isOwner" data-feature="F-19"><Icon name="link" class="icon-sm" /> {{ t('dash.lists.shared') }}</span>
              </template>
            </div>
          </div>
          <div class="list-head__actions">
            <button type="button" class="btn btn--secondary" v-if="canEdit" @click="edit"><Icon name="settings" /> {{ t('list.edit.title') }}</button>
            <button type="button" class="btn btn--primary" v-if="canAdd && items.length" @click="openItemDialog()" data-feature="F-14"><Icon name="plus" /> {{ t('list.item.add') }}</button>
          </div>
        </div>

        <div class="list-progress" v-if="items.length">
          <ProgressBar :done="progress.done" :total="progress.total" />
          <span class="list-progress__text">{{ t('dash.lists.claimed', { done: progress.done, total: progress.total }) }}</span>
        </div>

        <div class="alert" v-if="guest" data-feature="F-19"><Icon name="link" /><span>{{ t('list.guest.note') }}</span></div>
        <div class="alert alert--warning" v-if="isPending" data-feature="F-15">
          <Icon name="clock" />
          <span class="alert__body">
            {{ t('list.pending.note', { event: event.title }) }}
            <button type="button" class="btn btn--secondary btn--sm" @click="go('event', { currentEventId: event.id })">{{ t('list.pending.openEvent') }}</button>
          </span>
        </div>
        <div class="alert alert--success" v-if="allTaken"><Icon name="check" /><span><b>{{ t('list.allTaken.title') }}</b> {{ t('list.allTaken.text') }}</span></div>
      </div>

      <section class="card login-panel" v-if="login.open" aria-labelledby="guest-login-title" data-feature="F-19">
        <div class="login-panel__head">
          <h2 id="guest-login-title">{{ login.pending ? t('list.guest.loginToContinue') : t('welcome.login.title') }}</h2>
          <button type="button" class="btn btn--ghost btn--icon btn--sm" :aria-label="t('dialog.close')" @click="login.open = false"><Icon name="x" /></button>
        </div>
        <p class="muted" v-if="pendingLabel">{{ pendingLabel }}</p>
        <form class="form-grid" v-if="!login.sent" @submit.prevent="sendLink" novalidate data-feature="F-01">
          <FormField :label="t('welcome.login.email')" control="guest-email" required :error="login.error" :hint="t('welcome.login.note')">
            <input class="input" id="guest-email" type="email" v-model="login.email" placeholder="name@example.com" autocomplete="email" :aria-invalid="login.error ? 'true' : null">
          </FormField>
          <div class="row"><button type="submit" class="btn btn--primary">{{ t('welcome.login.sendLink') }}</button></div>
        </form>
        <div class="stack" v-else>
          <p><b>{{ t('welcome.login.sentTitle') }}</b> {{ t('welcome.login.sentText', { email: login.email }) }}</p>
          <div class="row">
            <button type="button" class="btn btn--primary" @click="completeLogin">{{ t('welcome.login.sentDemo') }}</button>
            <button type="button" class="btn btn--ghost" @click="login.sent = false">{{ t('welcome.login.again') }}</button>
          </div>
        </div>
      </section>

      <section class="section" v-if="items.length" aria-labelledby="items-title">
        <h2 id="items-title" class="visually-hidden">{{ t('list.items.title') }}</h2>
        <div class="item-group" v-for="entry in groups" :key="entry.key">
          <h3 class="item-group__label" v-if="grouped"><Icon name="calendar" class="icon-sm" /> {{ entry.label }}</h3>
          <ul class="items">
            <li :class="['card', 'item', remainingOf(item) === 0 ? 'item--done' : '']" v-for="item in entry.items" :key="item.id" data-feature="F-14">
              <div class="item__head">
                <div class="item__main">
                  <div class="item__title">
                    <span :class="['item__qty', item.quantity === null ? 'item__qty--task' : '']">{{ item.quantity === null ? t('list.item.task') : formatQuantity(item.quantity, item.unit) }}</span>
                    <span class="item__name">{{ item.name }}</span>
                  </div>
                  <p class="item__notes" v-if="item.notes">{{ item.notes }}</p>
                  <dl class="item__fields" v-if="fieldEntries(item).length" data-feature="F-16">
                    <div v-for="field in fieldEntries(item)" :key="field.id"><dt>{{ field.name }}</dt><dd>{{ field.value }}</dd></div>
                  </dl>
                  <button type="button" class="meta-chip meta-chip--link" v-if="!guest && item.attendanceOptionId && optionFor(item)" data-feature="F-18" @click="go('poll', { currentPollId: optionFor(item).pollId })"><Icon name="grid" class="icon-sm" /> {{ t('list.attendance.day', { date: formatDate(optionFor(item).date) }) }}</button>
                </div>
                <span :class="['item__status', remainingOf(item) === 0 ? 'item__status--done' : '']">{{ statusLabel(item) }}</span>
              </div>

              <ul class="item-claims" v-if="claimsOf(item).length" data-feature="F-15">
                <li class="item-claim" v-for="claim in claimsOf(item)" :key="claim.accountId">
                  <Avatar v-if="!guest || h.displayName(claim.accountId)" :id="claim.accountId" size="sm" />
                  <span class="avatar avatar--sm avatar--pending" v-else aria-hidden="true">?</span>
                  <span class="item-claim__name">{{ h.displayName(claim.accountId) || t('common.noName') }} <span class="soft" v-if="!guest && claim.accountId === user.id">({{ t('group.member.you') }})</span></span>
                  <span class="item-claim__qty" v-if="item.quantity !== null">{{ formatQuantity(claim.quantity, item.unit) }}</span>
                  <button type="button" class="btn btn--ghost btn--icon btn--sm" v-if="canRemoveClaim(claim)" :aria-label="t('list.claim.remove')" @click="removeClaim(claim)"><Icon name="x" /></button>
                </li>
              </ul>

              <div class="item__warn" v-if="warnFor(item)" data-feature="F-18"><Icon name="question" class="icon-sm" /> {{ warnFor(item) }}</div>

              <form class="claim-form" v-if="claimForm.itemId === item.id" @submit.prevent="submitClaim(item)" novalidate data-feature="F-15">
                <FormField :label="claimForm.accountId === user.id ? t('list.claim.howMuch') : t('list.claim.howMuchFor', { name: nameOf(claimForm.accountId) })" :control="'claim-qty-' + item.id" :error="claimForm.error" :hint="t('list.claim.left', { n: formatQuantity(remainingOf(item), item.unit) })">
                  <div class="claim-form__row">
                    <input class="input input--sm claim-form__input" :id="'claim-qty-' + item.id" type="number" min="0" step="any" inputmode="decimal" v-model="claimForm.quantity" :aria-invalid="claimForm.error ? 'true' : null">
                    <span class="claim-form__unit" v-if="item.unit">{{ item.unit }}</span>
                    <button type="submit" class="btn btn--primary btn--sm">{{ t('list.claim.confirm') }}</button>
                    <button type="button" class="btn btn--ghost btn--sm" @click="cancelClaim">{{ t('dialog.cancel') }}</button>
                  </div>
                </FormField>
              </form>

              <div class="item__actions" v-else>
                <template v-if="canClaim">
                  <button type="button" class="btn btn--primary btn--sm" v-if="!ownClaim(item) && remainingOf(item) > 0" data-feature="F-15" @click="item.quantity === null ? requestClaim(item, user.id, null) : startClaim(item)"><Icon name="check" /> {{ item.quantity === null ? t('list.claim.task') : t('list.claim.part') }}</button>
                  <button type="button" class="btn btn--secondary btn--sm" v-if="ownClaim(item) && item.quantity !== null" @click="startClaim(item)">{{ t('list.claim.change') }}</button>
                  <button type="button" class="btn btn--ghost btn--sm" v-if="ownClaim(item)" @click="removeClaim(ownClaim(item))">{{ t('list.claim.removeOwn') }}</button>
                </template>
                <span class="item__actions-spacer"></span>
                <button type="button" class="btn btn--ghost btn--sm item__comments" v-if="!guest && (commentCount(item) > 0 || openThreads.has(item.id))" :aria-expanded="openThreads.has(item.id)" :aria-controls="'thread-' + item.id" :aria-label="t('list.comments.count', { n: commentCount(item) })" @click="toggleThread(item)" data-feature="F-20"><Icon name="comment" /> {{ commentCount(item) }}</button>
                <ItemMenu v-if="!guest" :item-id="item.id" :candidates="canEdit && remainingOf(item) > 0 ? candidatesFor(item) : []" :can-comment="commentCount(item) === 0 && !openThreads.has(item.id)" :can-edit="canEdit" @claim-for="claimForId(item, $event)" @comment="openComment(item)" @edit="openItemDialog(item)" @delete="askDeleteItem(item)" />
              </div>

              <div class="item__thread" :id="'thread-' + item.id" v-if="!guest && openThreads.has(item.id)" data-feature="F-20">
                <CommentThread :list-id="list.id" :item-id="item.id" :can-post="true" :can-moderate="canEdit" compact />
              </div>
            </li>
          </ul>
        </div>
      </section>
      <EmptyState v-else :title="t('list.empty.title')" :text="canAdd ? t('list.empty.textAdd') : t('list.empty.textWait')">
        <button type="button" class="btn btn--primary" v-if="canAdd" @click="openItemDialog()" data-feature="F-14"><Icon name="plus" /> {{ t('list.empty.add') }}</button>
      </EmptyState>

      <section class="section" v-if="!guest" aria-labelledby="list-comments-title" data-feature="F-20">
        <div class="section-head">
          <h2 id="list-comments-title">{{ t('list.comments.title') }}<span class="section-head__count" v-if="listCommentCount">{{ listCommentCount }}</span></h2>
        </div>
        <div class="card"><CommentThread :list-id="list.id" :can-post="true" :can-moderate="canEdit" /></div>
      </section>

      <ItemDialog v-if="itemDialog" :list="list" :item="itemDialog.item" :can-delete="canEdit" @close="itemDialog = null" @delete="askDeleteItem" />
      <ConfirmDialog v-if="confirm" :title="confirm.title" :text="confirm.text" :confirm-label="confirm.confirmLabel" :danger="confirm.danger" @confirm="runConfirm" @cancel="confirm = null" />
    </main>

    <main class="container page" v-else>
      <div class="stack">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('list.notFound') }}</h1>
      </div>
    </main>`
};

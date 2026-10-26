// Notifications panel behind the bell (F-23): unread count, one entry per trigger, mark as read, jump to the target.

import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go } from './core.js';
import { t, formatDate, relativeDay } from './i18n.js';

const READ_AT = '2026-10-25T10:00';
const YESTERDAY = '2026-10-24';

function personName(id) {
  const account = h.account(id);

  if (!account) {
    return '';
  }

  return account.name || account.email;
}

function eventTarget(item, key, icon) {
  const event = h.event(item.refId);

  if (!event) {
    return null;
  }

  const group = h.group(event.groupId);

  return {
    view: 'event',
    ids: { currentEventId: event.id },
    icon,
    text: t(key, { name: personName(item.actorId), title: event.title, date: formatDate(event.start) }),
    context: group ? group.name : ''
  };
}

function itemTarget(item, key, icon) {
  const entry = data.items.find((row) => row.id === item.refId);
  const list = entry ? h.list(entry.listId) : null;

  if (!list) {
    return null;
  }

  return {
    view: 'list',
    ids: { currentListId: list.id },
    icon,
    text: t(key, { name: personName(item.actorId), item: entry.name }),
    context: list.title
  };
}

// Each trigger from F-23 resolves to a text, an icon and the view it opens. Entries whose reference was deleted in this session drop out.
const resolvers = {
  invited(item) {
    const group = h.group(item.refId);

    if (!group) {
      return null;
    }

    return { view: 'group', ids: { currentGroupId: group.id }, icon: 'users', text: t('notif.invited', { name: personName(item.actorId), group: group.name }), context: '' };
  },
  new_poll(item) {
    const poll = data.polls.find((row) => row.id === item.refId);

    if (!poll) {
      return null;
    }

    const group = h.group(poll.groupId);

    return { view: 'poll', ids: { currentPollId: poll.id }, icon: 'poll', text: t('notif.newPoll', { name: personName(item.actorId), title: poll.title }), context: group ? group.name : '' };
  },
  new_event: (item) => eventTarget(item, 'notif.newEvent', 'calendar'),
  fixed: (item) => eventTarget(item, 'notif.fixed', 'sparkle'),
  moved: (item) => eventTarget(item, 'notif.moved', 'clock'),
  comment(item) {
    const list = h.list(item.refId);

    if (!list) {
      return null;
    }

    return { view: 'list', ids: { currentListId: list.id }, icon: 'mail', text: t('notif.comment', { name: personName(item.actorId), list: list.title }), context: '' };
  },
  claimed_for: (item) => itemTarget(item, 'notif.claimedFor', 'check'),
  released: (item) => itemTarget(item, 'notif.released', 'minus')
};

function when(item) {
  const day = item.at.slice(0, 10);
  const time = item.at.slice(11, 16);

  if (day === YESTERDAY) {
    return `${t('notif.yesterday')}, ${time}`;
  }

  return `${relativeDay(day)}, ${time}`;
}

export const NotificationsBell = {
  setup() {
    const root = ref(null);
    const open = ref(false);
    const user = currentUser;

    const mine = computed(() => data.notifications
      .filter((item) => item.accountId === user.value.id)
      .sort((a, b) => b.at.localeCompare(a.at)));

    const entries = computed(() => mine.value
      .map((item) => {
        const resolve = resolvers[item.type];
        const target = resolve ? resolve(item) : null;

        if (!target) {
          return null;
        }

        return { id: item.id, item, unread: !item.readAt, when: when(item), ...target };
      })
      .filter(Boolean));

    const unread = computed(() => entries.value.filter((entry) => entry.unread).length);

    function toggle() {
      open.value = !open.value;
      ui.menuOpen = false;

      if (!open.value) {
        return;
      }

      nextTick(() => {
        const first = root.value && root.value.querySelector('.notif-panel button:not(:disabled)');

        if (first) {
          first.focus();
        }
      });
    }

    function close(returnFocus) {
      if (!open.value) {
        return;
      }

      open.value = false;

      if (returnFocus && root.value) {
        root.value.querySelector('.bell').focus();
      }
    }

    function openEntry(entry) {
      entry.item.readAt = READ_AT;
      open.value = false;
      go(entry.view, entry.ids);
    }

    function markAllRead() {
      mine.value.forEach((item) => {
        item.readAt = item.readAt || READ_AT;
      });
    }

    const onDocClick = (event) => {
      if (root.value && !root.value.contains(event.target)) {
        open.value = false;
      }
    };

    const onKey = (event) => {
      if (event.key === 'Escape') {
        close(true);
      }
    };

    onMounted(() => {
      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKey);
    });

    onBeforeUnmount(() => {
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKey);
    });

    return { root, open, entries, unread, toggle, openEntry, markAllRead, t };
  },
  template: `
    <div class="notifications" ref="root" data-feature="F-23">
      <button type="button" class="btn btn--ghost btn--icon bell" :aria-label="t('common.notifications.unread', { n: unread })" :aria-expanded="open" aria-controls="notifications-panel" @click="toggle">
        <Icon name="bell" />
        <span class="bell__count" v-if="unread" aria-hidden="true">{{ unread }}</span>
      </button>
      <div class="notif-panel" id="notifications-panel" v-if="open" role="dialog" :aria-label="t('nav.notifications')">
        <div class="notif-panel__head">
          <h2>{{ t('nav.notifications') }}</h2>
          <button type="button" class="btn btn--ghost btn--sm" :disabled="!unread" @click="markAllRead">{{ t('notif.markAllRead') }}</button>
        </div>
        <ul class="notif-list" v-if="entries.length">
          <li v-for="entry in entries" :key="entry.id">
            <button type="button" :class="['notif', entry.unread ? 'notif--unread' : '']" @click="openEntry(entry)">
              <span class="notif__icon"><Icon :name="entry.icon" /></span>
              <span class="notif__main">
                <span class="notif__text">{{ entry.text }}</span>
                <span class="notif__meta">{{ entry.when }}<template v-if="entry.context"> · {{ entry.context }}</template></span>
              </span>
              <span class="notif__dot" v-if="entry.unread"><span class="visually-hidden">{{ t('notif.unread') }}</span></span>
            </button>
          </li>
        </ul>
        <div class="notif-panel__empty" v-else>
          <EmptyState :title="t('notif.empty.title')" :text="t('notif.empty.text')" />
        </div>
        <p class="notif-panel__live" data-feature="F-21"><span class="badge badge--outline">{{ t('notif.live') }}</span> {{ t('notif.liveNote') }}</p>
      </div>
    </div>`
};

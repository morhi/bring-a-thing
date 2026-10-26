import { reactive, computed, nextTick, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import mockData from './mock-data.js';

export const data = reactive(structuredClone(mockData));

function readStored(key, fallback) {
  try {
    return localStorage.getItem(key) || fallback;
  } catch {
    return fallback;
  }
}

function writeStored(key, value) {
  try {
    localStorage.setItem(key, value);
  } catch {
    /* storage is a convenience only */
  }
}

export const ui = reactive({
  view: 'welcome',
  lang: readStored('bat.lang', 'en'),
  theme: readStored('bat.theme', 'system'),
  role: 'owner',
  showFeatures: false,
  reviewOpen: false,
  menuOpen: false,
  dialog: null,
  formContext: {},
  returnView: 'dashboard',
  toasts: [],
  states: {},
  currentGroupId: 'g1',
  joinToken: null, // invite-link: token the join page was opened with, so a regenerated link can fail
  currentEventId: null,
  currentPollId: null,
  currentListId: null
});

// The role switcher replaces authentication: each role maps to a member of the demo group.
const roleAccounts = { owner: 'u1', admin: 'u2', member: 'u3' };

export const currentUser = computed(() => data.accounts.find((account) => account.id === roleAccounts[ui.role]));

export const features = {
  'F-01': 'Login via magic link',
  'F-02': 'Account and settings',
  'F-03': 'Groups',
  'F-04': 'Inviting members',
  'F-05': 'Friends',
  'F-06': 'Date finder poll',
  'F-07': 'Attendance poll',
  'F-08': 'Editing and closing polls',
  'F-09': 'Fixing dates',
  'F-10': 'Events',
  'F-11': 'Participation',
  'F-12': 'Follow-up poll',
  'F-13': 'Lists',
  'F-14': 'Items',
  'F-15': 'Claiming',
  // list
  'F-16': 'Custom fields',
  'F-17': 'Duplicating lists',
  'F-18': 'Attendance link',
  'F-19': 'Share link',
  'F-20': 'Comments', // list
  'F-21': 'Live updates', // notifications
  'F-22': 'Dashboard',
  'F-23': 'Notifications',
  'F-24': 'Interface language'
};

export const views = {
  welcome: { component: 'WelcomeView', public: true, states: ['default', 'sent', 'password', 'error'] },
  dashboard: { component: 'DashboardView', states: ['default', 'caughtUp', 'empty'] },
  group: { component: 'GroupView', states: ['default', 'empty', 'noMembers'] },
  'group-settings': { component: 'GroupSettingsView', states: [] }, // group-settings
  'new-poll': { component: 'CreatePollView', states: [] },
  'new-event': { component: 'CreateEventView', states: [] },
  'new-list': { component: 'CreateListView', states: [] },
  'new-group': { component: 'NewGroupView', states: [] }, // new-group
  settings: { component: 'SettingsView', states: ['default', 'noPassword', 'noEmail'] }, // settings, invite-link
  // invite-link: the join page is public like the start page; the states override the real link state
  join: { component: 'JoinView', public: true, states: ['default', 'linkOpen', 'linkClosed', 'linkRevoked'] },
  friends: { component: 'FriendsView', states: ['default', 'empty'] }, // friends
  poll: { component: 'PollView', states: ['default', 'closed', 'noResponses', 'notInAudience', 'fixed', 'followUp', 'tie'] }, // poll, poll-rules: tie
  event: { component: 'EventView', states: ['default', 'past', 'noAcceptances', 'declined'] }, // event
  // list: the guest and invalid-link states hide the app header like a public page
  list: {
    component: 'ListView',
    states: ['default', 'empty', 'allTaken', 'guest', 'linkInvalid'],
    get public() {
      return ['guest', 'linkInvalid'].includes(ui.states.list);
    }
  }
};

export function go(view, ids = {}) {
  Object.assign(ui, ids);
  ui.view = view;
  ui.menuOpen = false;
  ui.dialog = null;
  window.scrollTo({ top: 0 });
  nextTick(() => {
    const heading = document.querySelector('main h1');

    if (!heading) {
      return;
    }

    heading.setAttribute('tabindex', '-1');
    heading.focus({ preventScroll: true });
  });
}

export function viewState(viewId) {
  return computed({
    get: () => ui.states[viewId] || 'default',
    set: (value) => { ui.states[viewId] = value; }
  });
}

// Create pages remember where they were opened from, so cancel and save return there.
export function goCreate(view, context = {}) {
  ui.formContext = context;
  ui.returnView = ui.view;
  go(view);
}

export function openDialog(name, props = {}) {
  ui.dialog = { name, props };
}

export function closeDialog() {
  ui.dialog = null;
}

let toastId = 0;

export function toast(message) {
  const id = ++toastId;
  ui.toasts.push({ id, message });
  setTimeout(() => {
    ui.toasts = ui.toasts.filter((item) => item.id !== id);
  }, 3200);
}

export function applyTheme() {
  const root = document.documentElement;

  if (ui.theme === 'system') {
    root.removeAttribute('data-theme');
  } else {
    root.setAttribute('data-theme', ui.theme);
  }

  writeStored('bat.theme', ui.theme);
}

watch(() => ui.theme, applyTheme, { immediate: true });
watch(() => ui.lang, (lang) => {
  document.documentElement.lang = lang;
  writeStored('bat.lang', lang);
}, { immediate: true });

let idCounter = 100;

export function nextId(prefix) {
  idCounter += 1;

  return `${prefix}${idCounter}`;
}

// invite-link: one link per group (F-04). The URL is a stand-in; the join view reads the token from ui.joinToken.
export function createInviteLink(group) {
  group.inviteLink = { token: nextId('inv-'), open: true };
}

export function inviteUrl(group) {
  return `${location.origin}${location.pathname}#join/${group.inviteLink.token}`;
}

export function copyText(text) {
  if (!navigator.clipboard) {
    return Promise.resolve();
  }

  // Clipboard access can be denied (headless, no focus); the toast still confirms the prototype action.
  return navigator.clipboard.writeText(text).catch(() => {});
}

// Query helpers over the mock data. Names follow requirements.md section 7.
export const h = {
  account: (id) => data.accounts.find((account) => account.id === id),
  displayName(id) {
    const account = h.account(id);

    return account && account.name ? account.name : null;
  },
  initials(id) {
    const account = h.account(id);

    if (!account) {
      return '?';
    }

    if (!account.name) {
      return account.email.slice(0, 2).toUpperCase();
    }

    return account.name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();
  },
  group: (id) => data.groups.find((group) => group.id === id),
  memberships: (groupId) => data.memberships.filter((membership) => membership.groupId === groupId),
  membersOf: (groupId) => h.memberships(groupId).map((membership) => membership.accountId),
  roleIn(groupId, accountId) {
    const membership = data.memberships.find((entry) => entry.groupId === groupId && entry.accountId === accountId);

    return membership ? membership.role : null;
  },
  canManage: (groupId, accountId) => ['owner', 'admin'].includes(h.roleIn(groupId, accountId)),
  groupsOf: (accountId) => data.groups.filter((group) => h.memberships(group.id).some((membership) => membership.accountId === accountId)),
  pollsOf: (groupId) => data.polls.filter((poll) => poll.groupId === groupId),
  options: (pollId) => data.pollOptions.filter((option) => option.pollId === pollId),
  responsesOf: (pollId, accountId) => {
    const ids = h.options(pollId).map((option) => option.id);

    return data.responses.filter((response) => ids.includes(response.optionId) && response.accountId === accountId);
  },
  respondents(pollId) {
    const ids = h.options(pollId).map((option) => option.id);

    return new Set(data.responses.filter((response) => ids.includes(response.optionId)).map((response) => response.accountId));
  },
  audience(poll) {
    if (poll.eventId) {
      return data.participations.filter((entry) => entry.eventId === poll.eventId && entry.status === 'accepted').map((entry) => entry.accountId);
    }

    return h.membersOf(poll.groupId);
  },
  event: (id) => data.events.find((event) => event.id === id),
  eventsOf: (groupId) => data.events.filter((event) => event.groupId === groupId),
  participation(eventId, accountId) {
    const entry = data.participations.find((item) => item.eventId === eventId && item.accountId === accountId);

    return entry ? entry.status : null;
  },
  participants: (eventId, status) => data.participations.filter((entry) => entry.eventId === eventId && (!status || entry.status === status)),
  list: (id) => data.lists.find((list) => list.id === id),
  listsOfGroup: (groupId) => data.lists.filter((list) => list.groupId === groupId),
  listsOfEvent: (eventId) => data.lists.filter((list) => list.eventId === eventId),
  standaloneLists: (accountId) => data.lists.filter((list) => !list.groupId && !list.eventId && list.ownerId === accountId),
  items: (listId) => data.items.filter((item) => item.listId === listId),
  claimsOf: (itemId) => data.claims.filter((claim) => claim.itemId === itemId),
  listProgress(listId) {
    const items = h.items(listId);
    const done = items.filter((item) => {
      const claims = h.claimsOf(item.id);

      if (item.quantity === null) {
        return claims.length > 0;
      }

      return claims.reduce((sum, claim) => sum + (claim.quantity || 0), 0) >= item.quantity;
    }).length;

    return { done, total: items.length };
  },
  listDate(list) {
    if (list.date) {
      return list.date;
    }

    if (list.eventId) {
      return h.event(list.eventId).start;
    }

    return null;
  },
  myClaims(accountId) {
    return data.claims
      .filter((claim) => claim.accountId === accountId)
      .map((claim) => {
        const item = data.items.find((entry) => entry.id === claim.itemId);
        const list = h.list(item.listId);

        return { claim, item, list, date: h.listDate(list) };
      });
  },
  unread: (accountId) => data.notifications.filter((item) => item.accountId === accountId && !item.readAt).length,
  friendsOf: (accountId) => data.friends.filter((entry) => entry.accountId === accountId).map((entry) => h.account(entry.friendId))
};

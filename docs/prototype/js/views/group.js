// Group page (F-03, F-04): members with invite, polls, events, group lists, create actions.

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, currentUser, go, goCreate, viewState, toast } from '../core.js';
import { t, formatDate, relativeDay, isPast } from '../i18n.js';
import { InviteField, inviteToGroup, invitedToast } from '../invite-field.js'; // new-group

// One poll row; closed and fixed polls render the same row inside the collapsed section.
const PollRow = {
  props: { poll: { type: Object, required: true } },
  setup() {
    function pollMeta(poll) {
      const total = h.audience(poll).length;
      const answered = Array.from(h.respondents(poll.id)).filter((id) => h.audience(poll).includes(id)).length;

      return { total, answered, options: h.options(poll.id).length };
    }

    function pollStatus(poll) {
      if (!poll.closedAt) {
        return { label: t('group.polls.open'), cls: 'badge--primary' };
      }

      if (h.options(poll.id).some((option) => option.fixed)) {
        return { label: t('group.polls.fixed'), cls: 'badge--success' };
      }

      return { label: t('group.polls.closed'), cls: '' };
    }

    return { pollMeta, pollStatus, go, h, t };
  },
  template: `
    <button type="button" class="card card--interactive poll-row" @click="go('poll', { currentPollId: poll.id })" :data-feature="poll.eventId ? 'F-12' : (poll.type === 'attendance' ? 'F-07' : 'F-06')">
      <span :class="['poll-row__icon', !poll.closedAt ? 'poll-row__icon--open' : '']"><Icon :name="poll.type === 'attendance' ? 'grid' : 'poll'" /></span>
      <span class="poll-row__main">
        <span class="poll-row__title">{{ poll.title }}</span>
        <span class="poll-row__meta">
          <span>{{ poll.type === 'attendance' ? t('group.polls.attendance') : t('group.polls.dateFinder') }}</span>
          <span>{{ t('group.polls.options', { n: pollMeta(poll).options }) }}</span>
          <span v-if="poll.eventId" class="badge badge--primary badge--regular">{{ t('group.polls.followUp', { event: h.event(poll.eventId).title }) }}</span>
        </span>
      </span>
      <span class="poll-row__aside">
        <span class="response-count" v-if="!poll.closedAt">
          <ProgressBar :done="pollMeta(poll).answered" :total="pollMeta(poll).total" />
          {{ pollMeta(poll).answered }}/{{ pollMeta(poll).total }}
        </span>
        <span :class="['badge', pollStatus(poll).cls]">{{ pollStatus(poll).label }}</span>
      </span>
    </button>`
};

export const GroupView = {
  components: { PollRow, InviteField },
  setup() {
    const state = viewState('group');
    const user = currentUser;
    const group = computed(() => h.group(ui.currentGroupId));
    const canManage = computed(() => h.canManage(group.value.id, user.value.id));
    const isOwner = computed(() => h.roleIn(group.value.id, user.value.id) === 'owner');

    const members = computed(() => {
      if (state.value === 'noMembers') {
        return h.memberships(group.value.id).filter((membership) => membership.accountId === user.value.id);
      }

      const order = { owner: 0, admin: 1, member: 2 };

      return [...h.memberships(group.value.id)].sort((a, b) => order[a.role] - order[b.role] || (h.displayName(a.accountId) || 'zz').localeCompare(h.displayName(b.accountId) || 'zz'));
    });

    const polls = computed(() => {
      if (state.value !== 'default') {
        return [];
      }

      return h.pollsOf(group.value.id)
        .filter((poll) => h.audience(poll).includes(user.value.id) || !poll.eventId)
        .sort((a, b) => (a.closedAt ? 1 : 0) - (b.closedAt ? 1 : 0) || b.createdAt.localeCompare(a.createdAt));
    });

    const openPolls = computed(() => polls.value.filter((poll) => !poll.closedAt));
    const closedPolls = computed(() => polls.value.filter((poll) => poll.closedAt));
    const closedOpen = ref(false);

    const events = computed(() => (state.value === 'default' ? h.eventsOf(group.value.id) : []));
    const upcomingEvents = computed(() => events.value.filter((event) => !isPast(event.start)).sort((a, b) => a.start.localeCompare(b.start)));
    const pastEvents = computed(() => events.value.filter((event) => isPast(event.start)).sort((a, b) => b.start.localeCompare(a.start)));
    const lists = computed(() => (state.value === 'default' ? h.listsOfGroup(group.value.id) : []));

    const stats = computed(() => ({
      members: members.value.length,
      openPolls: polls.value.filter((poll) => !poll.closedAt).length,
      upcoming: upcomingEvents.value.length
    }));

    // new-group: the shared invitation component collects chips, "Invite" applies them (F-04, F-05)
    const invites = ref([]);

    function invite() {
      const invited = inviteToGroup(group.value, invites.value, user.value.id);
      invites.value = [];

      if (!invited.length) {
        return;
      }

      // The simulated "no other members" state would hide the new member, so a real invite leaves it.
      state.value = 'default';
      toast(invitedToast(invited));
    }

    function myStatus(eventId) {
      const status = h.participation(eventId, user.value.id);

      if (status === 'accepted') {
        return { label: t('group.events.youAccepted'), cls: 'badge--success' };
      }

      if (status === 'declined') {
        return { label: t('group.events.youDeclined'), cls: 'badge--danger' };
      }

      return { label: t('group.events.youPending'), cls: 'badge--warning' };
    }

    return {
      ui, state, user, group, canManage, isOwner, members, polls, openPolls, closedPolls, closedOpen, upcomingEvents, pastEvents, lists, stats,
      invites, invite,
      myStatus, go, goCreate, h, t, formatDate, relativeDay
    };
  },
  template: `
    <main class="container page" v-if="group">
      <div class="group-head" data-feature="F-03">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <div class="group-head__top">
          <div class="group-head__title">
            <h1>{{ group.name }} <span class="badge" v-if="h.roleIn(group.id, user.id) !== 'member'">{{ t('group.member.' + h.roleIn(group.id, user.id)) }}</span></h1>
            <div class="group-head__meta">
              <span><b>{{ stats.members }}</b> {{ t('word.members') }}</span>
              <span v-if="stats.openPolls"><b>{{ stats.openPolls }}</b> {{ t('word.openPolls') }}</span>
              <span v-if="stats.upcoming"><b>{{ stats.upcoming }}</b> {{ t('word.upcoming') }}</span>
            </div>
          </div>
          <button type="button" class="btn btn--ghost btn--sm" v-if="canManage" @click="go('group-settings', { currentGroupId: group.id })" data-feature="F-03"><Icon name="settings" /> {{ t('group.settings') }}</button>
        </div>
        <div class="create-bar">
          <button type="button" class="btn btn--primary" @click="goCreate('new-poll', { groupId: group.id })" data-feature="F-06"><Icon name="poll" /> {{ t('group.new.poll') }}</button>
          <button type="button" class="btn btn--secondary" @click="goCreate('new-event', { groupId: group.id })" data-feature="F-10"><Icon name="calendar" /> {{ t('group.new.event') }}</button>
          <button type="button" class="btn btn--secondary" @click="goCreate('new-list', { groupId: group.id })" data-feature="F-13"><Icon name="list" /> {{ t('group.new.list') }}</button>
        </div>
      </div>

      <div class="group-body">
        <div class="group-main">
          <section class="section" aria-labelledby="polls-title" data-feature="F-06">
            <div class="section-head">
              <h2 id="polls-title">{{ t('group.polls') }}<span class="section-head__count" v-if="polls.length">{{ polls.length }}</span></h2>
            </div>
            <div class="stack" v-if="polls.length">
              <PollRow v-for="poll in openPolls" :key="poll.id" :poll="poll" />
              <template v-if="closedPolls.length">
                <button type="button" class="btn btn--ghost btn--sm poll-closed-toggle" :aria-expanded="closedOpen" aria-controls="closed-polls" data-feature="F-08" @click="closedOpen = !closedOpen">
                  <Icon name="chevron" :class="['icon-sm', 'poll-closed-toggle__icon', closedOpen ? 'poll-closed-toggle__icon--open' : '']" /> {{ t('group.polls.closedSection', { n: closedPolls.length }) }}
                </button>
                <div class="stack" id="closed-polls" v-if="closedOpen">
                  <PollRow v-for="poll in closedPolls" :key="poll.id" :poll="poll" />
                </div>
              </template>
            </div>
            <EmptyState v-else :title="t('group.polls.empty.title')" :text="t('group.polls.empty.text')">
              <button type="button" class="btn btn--primary" @click="goCreate('new-poll', { groupId: group.id })"><Icon name="plus" /> {{ t('group.new.poll') }}</button>
            </EmptyState>
          </section>

          <section class="section" aria-labelledby="events-title" data-feature="F-10">
            <div class="section-head">
              <h2 id="events-title">{{ t('group.events') }}<span class="section-head__count" v-if="upcomingEvents.length + pastEvents.length">{{ upcomingEvents.length + pastEvents.length }}</span></h2>
            </div>
            <template v-if="upcomingEvents.length || pastEvents.length">
              <div class="event-group" v-if="upcomingEvents.length">
                <span class="event-group__label">{{ t('group.events.upcoming') }}</span>
                <button type="button" class="card card--interactive event-row" v-for="event in upcomingEvents" :key="event.id" @click="go('event', { currentEventId: event.id })">
                  <DateBlock :date="event.start" :fixed="!!event.originOptionId" />
                  <span class="event-row__main">
                    <span class="event-row__title">{{ event.title }} <span class="badge badge--outline" v-if="event.originOptionId" data-feature="F-09">{{ t('group.events.fromPoll') }}</span></span>
                    <span class="event-row__meta">
                      <span>{{ formatDate(event.start, 'weekday') }}<template v-if="event.startTime">, {{ event.startTime }}</template></span>
                      <span v-if="event.location">{{ event.location }}</span>
                    </span>
                    <span class="event-row__meta">
                      <span>{{ t('group.events.accepted', { n: h.participants(event.id, 'accepted').length }) }}</span>
                      <span v-if="h.participants(event.id, 'pending').length">{{ t('group.events.pending', { n: h.participants(event.id, 'pending').length }) }}</span>
                    </span>
                  </span>
                  <span class="event-row__aside" data-feature="F-11">
                    <span :class="['badge', myStatus(event.id).cls]">{{ myStatus(event.id).label }}</span>
                  </span>
                </button>
              </div>
              <div class="event-group" v-if="pastEvents.length">
                <span class="event-group__label">{{ t('group.events.past') }}</span>
                <button type="button" class="card card--interactive event-row event-row--past" v-for="event in pastEvents" :key="event.id" @click="go('event', { currentEventId: event.id })">
                  <DateBlock :date="event.start" />
                  <span class="event-row__main">
                    <span class="event-row__title">{{ event.title }}</span>
                    <span class="event-row__meta">
                      <span>{{ formatDate(event.start, 'weekday') }}</span>
                      <span>{{ t('group.events.accepted', { n: h.participants(event.id, 'accepted').length }) }}</span>
                    </span>
                  </span>
                </button>
              </div>
            </template>
            <EmptyState v-else :title="t('group.events.empty.title')" :text="t('group.events.empty.text')">
              <button type="button" class="btn btn--secondary" @click="goCreate('new-event', { groupId: group.id })"><Icon name="plus" /> {{ t('group.new.event') }}</button>
            </EmptyState>
          </section>

          <section class="section" aria-labelledby="lists-title" data-feature="F-13">
            <div class="section-head">
              <h2 id="lists-title">{{ t('group.lists') }}<span class="section-head__count" v-if="lists.length">{{ lists.length }}</span></h2>
              <span class="notice">{{ t('group.lists.eventLists') }}</span>
            </div>
            <div class="grid grid--2" v-if="lists.length">
              <button type="button" class="card card--interactive list-card" v-for="list in lists" :key="list.id" @click="go('list', { currentListId: list.id })">
                <span class="list-card__icon"><Icon name="list" /></span>
                <span class="row-item__main">
                  <span class="row-item__title">{{ list.title }}</span>
                  <span class="row-item__meta"><template v-if="list.date">{{ formatDate(list.date) }} · </template>{{ t('dash.lists.claimed', { done: h.listProgress(list.id).done, total: h.listProgress(list.id).total }) }}</span>
                  <span class="row row--progress">
                    <ProgressBar :done="h.listProgress(list.id).done" :total="h.listProgress(list.id).total" />
                  </span>
                </span>
              </button>
            </div>
            <EmptyState v-else :title="t('group.lists.empty.title')" :text="t('group.lists.empty.text')">
              <button type="button" class="btn btn--secondary" @click="goCreate('new-list', { groupId: group.id })"><Icon name="plus" /> {{ t('group.new.list') }}</button>
            </EmptyState>
          </section>
        </div>

        <aside class="group-side" aria-labelledby="members-title">
          <section class="card members" data-feature="F-04">
            <div class="section-head">
              <h2 id="members-title">{{ t('group.members') }}<span class="section-head__count">{{ members.length }}</span></h2>
            </div>
            <ul class="rows">
              <li class="member" v-for="membership in members" :key="membership.accountId">
                <Avatar :id="membership.accountId" />
                <span class="member__main">
                  <span class="member__name">
                    <span v-if="h.displayName(membership.accountId)">{{ h.displayName(membership.accountId) }}</span>
                    <span class="soft" v-else>{{ t('common.noName') }}</span>
                    <span class="soft small" v-if="membership.accountId === user.id">({{ t('group.member.you') }})</span>
                    <span class="badge badge--warning" v-if="!membership.acceptedAt">{{ t('group.member.pending') }}</span>
                    <span class="badge badge--info" v-if="membership.joinedViaLink" data-feature="F-04">{{ t('group.member.viaLink') }}</span>
                  </span>
                  <span class="member__email" v-if="h.account(membership.accountId).email">{{ h.account(membership.accountId).email }}</span>
                </span>
                <span class="member__role" v-if="membership.role !== 'member'">{{ t('group.member.' + membership.role) }}</span>
              </li>
            </ul>

            <InviteField v-if="canManage" v-model="invites" :group-id="group.id" :label="t('group.invite.title')" :hint="t('group.invite.hint')" :submit-label="t('group.invite.button')" id="group-invite" @submit="invite" />
            <p class="notice" v-else>{{ t('group.memberNotice') }}</p>
          </section>
        </aside>
      </div>
    </main>
    <main class="container page" v-else><h1>{{ t('group.notFound') }}</h1></main>`
};

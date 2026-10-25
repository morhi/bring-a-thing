// Dashboard (F-22): waiting for you, upcoming events, claims, groups, standalone lists.

import { computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, openDialog, goCreate, viewState } from '../core.js';
import { t, formatDate, formatLongDate, relativeDay, isPast, TODAY } from '../i18n.js';

export const DashboardView = {
  setup() {
    const state = viewState('dashboard');
    const user = currentUser;

    const greeting = computed(() => {
      const hour = TODAY.getHours();
      const key = hour < 11 ? 'morning' : hour < 18 ? 'day' : 'evening';

      return t(`dash.greeting.${key}`, { name: user.value.name.split(' ')[0] });
    });

    const waiting = computed(() => {
      if (state.value !== 'default') {
        return [];
      }

      const items = [];

      data.polls.filter((poll) => !poll.closedAt && h.audience(poll).includes(user.value.id)).forEach((poll) => {
        const total = h.options(poll.id).length;
        const answered = h.responsesOf(poll.id, user.value.id).length;

        if (answered < total) {
          items.push({ kind: 'poll', id: poll.id, title: poll.title, group: h.group(poll.groupId).name, answered, total, event: poll.eventId ? h.event(poll.eventId) : null });
        }
      });

      data.events.filter((event) => !isPast(event.start) && h.participation(event.id, user.value.id) === 'pending').forEach((event) => {
        items.push({ kind: 'event', id: event.id, title: event.title, group: h.group(event.groupId).name, date: event.start, time: event.startTime, accepted: h.participants(event.id, 'accepted').length });
      });

      return items;
    });

    const upcoming = computed(() => {
      if (state.value === 'empty') {
        return [];
      }

      return data.events
        .filter((event) => !isPast(event.start) && h.participation(event.id, user.value.id) === 'accepted')
        .sort((a, b) => a.start.localeCompare(b.start));
    });

    const claims = computed(() => {
      if (state.value === 'empty') {
        return [];
      }

      return h.myClaims(user.value.id)
        .filter((entry) => !entry.date || !isPast(entry.date))
        .sort((a, b) => (a.date || '9').localeCompare(b.date || '9'));
    });

    const groups = computed(() => (state.value === 'empty' ? [] : h.groupsOf(user.value.id)));
    const lists = computed(() => (state.value === 'empty' ? [] : h.standaloneLists(user.value.id)));

    function groupStats(group) {
      return {
        members: h.memberships(group.id).length,
        openPolls: h.pollsOf(group.id).filter((poll) => !poll.closedAt).length,
        upcoming: h.eventsOf(group.id).filter((event) => !isPast(event.start)).length
      };
    }

    function claimLabel(entry) {
      if (entry.item.quantity === null) {
        return '';
      }

      return `${entry.claim.quantity}${entry.item.unit ? ' ' + entry.item.unit : ''}`;
    }

    function openGroup(id) {
      go('group', { currentGroupId: id });
    }

    return { ui, state, user, greeting, waiting, upcoming, claims, groups, lists, groupStats, claimLabel, openGroup, go, openDialog, goCreate, h, t, formatDate, formatLongDate, relativeDay };
  },
  template: `
    <main class="container page" data-feature="F-22">
      <div class="greeting">
        <h1>{{ greeting }}</h1>
        <span class="greeting__date">{{ formatLongDate() }}</span>
      </div>

      <section class="waiting" aria-labelledby="waiting-title">
        <div class="waiting__head">
          <h2 id="waiting-title">{{ t('dash.waiting') }} <span class="waiting__count" v-if="waiting.length">{{ waiting.length }}</span></h2>
        </div>
        <div class="waiting__list" v-if="waiting.length">
          <button type="button" class="waiting-item" v-for="item in waiting" :key="item.kind + item.id" @click="go(item.kind, item.kind === 'poll' ? { currentPollId: item.id } : { currentEventId: item.id })" :data-feature="item.kind === 'poll' ? (item.event ? 'F-12' : 'F-06') : 'F-11'">
            <span class="waiting-item__icon"><Icon :name="item.kind === 'poll' ? 'poll' : 'calendar'" /></span>
            <span class="waiting-item__main">
              <span class="waiting-item__title">{{ item.title }}</span>
              <span class="waiting-item__meta" v-if="item.kind === 'poll'">{{ item.group }} · {{ item.answered ? t('dash.waiting.pollMeta', { answered: item.answered, total: item.total }) : t('dash.waiting.pollMetaNone') }}</span>
              <span class="waiting-item__meta" v-else>{{ item.group }} · {{ formatDate(item.date, 'weekday') }}<template v-if="item.time">, {{ item.time }}</template></span>
            </span>
            <span class="waiting-item__cta">{{ item.kind === 'poll' ? t('dash.waiting.poll') : t('dash.waiting.event') }}</span>
          </button>
        </div>
        <div class="waiting__done" v-else>
          <Mark answer="yes" />
          <span>{{ t('dash.waiting.done') }}</span>
        </div>
      </section>

      <div class="dash-split">
        <section class="section" aria-labelledby="upcoming-title">
          <div class="section-head">
            <h2 id="upcoming-title">{{ t('dash.upcoming') }}<span class="section-head__count" v-if="upcoming.length">{{ upcoming.length }}</span></h2>
          </div>
          <div class="stack" v-if="upcoming.length">
            <button type="button" class="card card--interactive event-card" v-for="event in upcoming" :key="event.id" @click="go('event', { currentEventId: event.id })" data-feature="F-10">
              <DateBlock :date="event.start" :fixed="!!event.originOptionId" />
              <span class="event-card__main">
                <span class="event-card__title">{{ event.title }}</span>
                <span class="event-card__meta">
                  <span>{{ h.group(event.groupId).name }}</span>
                  <span v-if="event.startTime"><Icon name="clock" class="icon-sm icon-inline" /> {{ event.startTime }}</span>
                  <span v-if="event.location">{{ event.location }}</span>
                </span>
                <span class="event-card__foot">
                  <AvatarStack :ids="h.participants(event.id, 'accepted').map(p => p.accountId)" :max="5" />
                  <span class="badge badge--primary">{{ relativeDay(event.start) }}</span>
                </span>
              </span>
            </button>
          </div>
          <EmptyState v-else :title="t('dash.upcoming.empty.title')" :text="t('dash.upcoming.empty.text')" />
        </section>

        <section class="section" aria-labelledby="claims-title" data-feature="F-15">
          <div class="section-head">
            <h2 id="claims-title">{{ t('dash.claims') }}<span class="section-head__count" v-if="claims.length">{{ claims.length }}</span></h2>
          </div>
          <div class="card claims" v-if="claims.length">
            <button type="button" class="claim" v-for="entry in claims" :key="entry.item.id" @click="go('list', { currentListId: entry.list.id })">
              <span class="claim__qty">{{ claimLabel(entry) || '✓' }}</span>
              <span class="row-item__main">
                <span class="claim__name">{{ entry.item.name }}</span>
                <span class="claim__for">{{ t('dash.claims.for', { list: entry.list.title }) }}<template v-if="entry.date"> · {{ formatDate(entry.date) }}</template></span>
              </span>
              <Icon name="chevron" class="icon-md icon-soft" />
            </button>
          </div>
          <p class="muted" v-else>{{ t('dash.claims.empty') }}</p>
        </section>
      </div>

      <section class="section" aria-labelledby="groups-title" data-feature="F-03">
        <div class="section-head">
          <h2 id="groups-title">{{ t('dash.groups') }}<span class="section-head__count" v-if="groups.length">{{ groups.length }}</span></h2>
          <button type="button" class="btn btn--secondary btn--sm" @click="openDialog('newGroup')"><Icon name="plus" /> {{ t('dash.groups.new') }}</button>
        </div>
        <div class="grid grid--3" v-if="groups.length">
          <button type="button" class="card card--interactive group-card" v-for="group in groups" :key="group.id" @click="openGroup(group.id)">
            <span class="group-card__head">
              <span class="group-card__title">{{ group.name }}</span>
              <span class="badge" v-if="h.roleIn(group.id, user.id) !== 'member'">{{ t('group.member.' + h.roleIn(group.id, user.id)) }}</span>
            </span>
            <AvatarStack :ids="h.membersOf(group.id)" :max="6" />
            <span class="group-card__stats">
              <span><b>{{ groupStats(group).members }}</b> {{ t('word.members') }}</span>
              <span v-if="groupStats(group).openPolls"><b>{{ groupStats(group).openPolls }}</b> {{ t('word.openPolls') }}</span>
              <span v-if="groupStats(group).upcoming"><b>{{ groupStats(group).upcoming }}</b> {{ t('word.upcoming') }}</span>
            </span>
          </button>
        </div>
        <EmptyState v-else :title="state === 'empty' ? t('dash.empty.title') : t('dash.groups.empty.title')" :text="state === 'empty' ? t('dash.empty.text') : t('dash.groups.empty.text')">
          <button type="button" class="btn btn--primary" @click="openDialog('newGroup')"><Icon name="plus" /> {{ t('dash.groups.new') }}</button>
          <button type="button" class="btn btn--secondary" v-if="state === 'empty'" @click="goCreate('new-list')">{{ t('dash.lists.new') }}</button>
        </EmptyState>
      </section>

      <section class="section" aria-labelledby="lists-title" data-feature="F-13">
        <div class="section-head">
          <h2 id="lists-title">{{ t('dash.lists') }}<span class="section-head__count" v-if="lists.length">{{ lists.length }}</span></h2>
          <button type="button" class="btn btn--secondary btn--sm" @click="goCreate('new-list')"><Icon name="plus" /> {{ t('dash.lists.new') }}</button>
        </div>
        <div class="grid grid--2" v-if="lists.length">
          <button type="button" class="card card--interactive list-card" v-for="list in lists" :key="list.id" @click="go('list', { currentListId: list.id })">
            <span class="list-card__icon"><Icon name="list" /></span>
            <span class="row-item__main">
              <span class="row-item__title">{{ list.title }}</span>
              <span class="row-item__meta">
                <template v-if="list.date">{{ formatDate(list.date) }} · </template>{{ t('dash.lists.claimed', { done: h.listProgress(list.id).done, total: h.listProgress(list.id).total }) }}
              </span>
              <span class="row row--progress">
                <ProgressBar :done="h.listProgress(list.id).done" :total="h.listProgress(list.id).total" />
                <span class="badge badge--outline" v-if="list.shareToken" data-feature="F-19"><Icon name="link" class="icon-sm" /> {{ t('dash.lists.shared') }}</span>
              </span>
            </span>
          </button>
        </div>
        <EmptyState v-else-if="state !== 'empty'" :title="t('dash.lists.empty.title')" :text="t('dash.lists.empty.text')">
          <button type="button" class="btn btn--secondary" @click="goCreate('new-list')"><Icon name="plus" /> {{ t('dash.lists.new') }}</button>
        </EmptyState>
      </section>
    </main>`
};

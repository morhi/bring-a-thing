// Poll page (F-06 to F-09, F-12): header, response grid with best options and totals,
// fixing dates, closing and reopening. Editing happens on the poll edit page (views/create.js).

import { ref, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, goCreate, viewState, toast, nextId } from '../core.js';
import { t, formatDate, weekdayShort, dayNumber, monthShort } from '../i18n.js';

const TODAY_ISO = '2026-10-25';
const ANSWERS = ['yes', 'maybe', 'no'];

// Review bar states that need a specific poll from the mock data; default follows navigation.
const statePolls = { closed: 'p7', noResponses: 'p8', notInAudience: 'p9', fixed: 'p4', followUp: 'p2', tie: 'p11' }; // poll-rules: tie

function findPoll(id) {
  return data.polls.find((poll) => poll.id === id);
}

function sortOptions(options) {
  return [...options].sort((a, b) => a.date.localeCompare(b.date) || (a.start || '').localeCompare(b.start || ''));
}

function optionTime(option) {
  if (!option.start) {
    return '';
  }

  return `${option.start}-${option.end}`;
}

function answerOf(optionId, accountId) {
  const response = data.responses.find((entry) => entry.optionId === optionId && entry.accountId === accountId);

  return response ? response.answer : 'none';
}

function tally(optionId, accountIds) {
  const counts = { yes: 0, maybe: 0, no: 0 };

  data.responses
    .filter((entry) => entry.optionId === optionId && accountIds.includes(entry.accountId))
    .forEach((entry) => { counts[entry.answer] += 1; });

  return counts;
}

function nameOf(accountId) {
  return h.displayName(accountId) || h.account(accountId).email;
}

const PollFixDialog = {
  props: {
    poll: { type: Object, required: true },
    options: { type: Array, required: true },
    audience: { type: Array, required: true },
    totals: { type: Object, required: true },
    best: { type: Array, required: true }
  },
  emits: ['close'],
  setup(props, { emit }) {
    const selected = ref([]);

    const preview = computed(() => props.options
      .filter((option) => selected.value.includes(option.id))
      .map((option) => ({
        option,
        accepted: props.audience.filter((id) => answerOf(option.id, id) === 'yes'),
        pending: props.audience.filter((id) => answerOf(option.id, id) === 'maybe')
      })));

    const confirmLabel = computed(() => {
      if (!selected.value.length) {
        return t('poll.fix.confirmNone');
      }

      return selected.value.length === 1 ? t('poll.fix.confirmOne') : t('poll.fix.confirmMany', { n: selected.value.length });
    });

    function confirm() {
      if (!preview.value.length) {
        return;
      }

      preview.value.forEach(({ option, accepted, pending }) => {
        option.fixed = true;
        const event = {
          id: nextId('e'),
          groupId: props.poll.groupId,
          organizerId: props.poll.organizerId,
          title: props.poll.title,
          description: '',
          location: '',
          start: option.date,
          startTime: option.start,
          end: null,
          originOptionId: option.id,
          parentEventId: props.poll.eventId || null
        };
        data.events.push(event);
        accepted.forEach((accountId) => data.participations.push({ eventId: event.id, accountId, status: 'accepted' }));
        pending.forEach((accountId) => data.participations.push({ eventId: event.id, accountId, status: 'pending' }));
      });

      props.poll.closedAt = TODAY_ISO;
      toast(t('poll.fix.done'));
      emit('close');
    }

    return { selected, preview, confirmLabel, confirm, optionTime, t, formatDate };
  },
  template: `
    <AppDialog :title="t('poll.fix.title')" :subtitle="t('poll.fix.sub')" wide @close="$emit('close')">
      <FormField :label="t('poll.fix.options')" group>
        <div class="fix-options">
          <label :class="['fix-option', option.fixed ? 'fix-option--fixed' : '']" v-for="option in options" :key="option.id">
            <input type="checkbox" :value="option.id" v-model="selected" :disabled="option.fixed">
            <span class="fix-option__main">
              <span class="fix-option__date">{{ formatDate(option.date, 'weekday') }}<template v-if="option.start">, {{ optionTime(option) }}</template><template v-if="option.label"> · {{ option.label }}</template></span>
              <span class="fix-option__counts">{{ t('poll.totals.yes', { n: totals[option.id].yes }) }}, {{ t('poll.totals.maybe', { n: totals[option.id].maybe }) }}, {{ t('poll.totals.no', { n: totals[option.id].no }) }}</span>
            </span>
            <span class="badge badge--success" v-if="best.includes(option.id)">{{ t('poll.best') }}</span>
            <span class="badge" v-if="option.fixed">{{ t('poll.fix.alreadyFixed') }}</span>
          </label>
        </div>
      </FormField>

      <div class="fix-preview" aria-live="polite">
        <p class="fix-preview__lead" v-if="!preview.length">{{ t('poll.fix.previewNone') }}</p>
        <template v-else>
          <p class="fix-preview__lead"><b>{{ preview.length === 1 ? t('poll.fix.previewOne') : t('poll.fix.previewMany', { n: preview.length }) }}</b> {{ t('poll.fix.derived') }}</p>
          <ul class="stack stack--tight">
            <li class="fix-event" v-for="entry in preview" :key="entry.option.id">
              <DateBlock :date="entry.option.date" fixed />
              <span class="fix-event__main">
                <span class="fix-event__title">{{ poll.title }}</span>
                <span class="fix-event__meta">{{ formatDate(entry.option.date, 'weekday') }}<template v-if="entry.option.start">, {{ optionTime(entry.option) }}</template></span>
                <span class="fix-event__people">
                  <span class="fix-event__group" v-if="entry.accepted.length"><AvatarStack :ids="entry.accepted" /> {{ t('group.events.accepted', { n: entry.accepted.length }) }}</span>
                  <span class="fix-event__group" v-if="entry.pending.length"><AvatarStack :ids="entry.pending" /> {{ t('group.events.pending', { n: entry.pending.length }) }}</span>
                  <span class="soft small" v-if="!entry.accepted.length && !entry.pending.length">{{ t('poll.fix.noParticipants') }}</span>
                </span>
              </span>
            </li>
          </ul>
        </template>
      </div>

      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="button" class="btn btn--primary" :disabled="!selected.length" @click="confirm" data-feature="F-09">{{ confirmLabel }}</button>
      </template>
    </AppDialog>`
};

export const PollView = {
  components: { PollFixDialog },
  setup() {
    const state = viewState('poll');
    const user = currentUser;
    const fixOpen = ref(false);

    const poll = computed(() => findPoll(statePolls[state.value] || ui.currentPollId));
    const group = computed(() => (poll.value ? h.group(poll.value.groupId) : null));
    const event = computed(() => (poll.value && poll.value.eventId ? h.event(poll.value.eventId) : null));
    const audience = computed(() => (poll.value ? h.audience(poll.value) : []));
    const visible = computed(() => !!poll.value && state.value !== 'notInAudience' && audience.value.includes(user.value.id));
    const canEdit = computed(() => visible.value && (poll.value.organizerId === user.value.id || h.canManage(poll.value.groupId, user.value.id)));
    const isOpen = computed(() => !!poll.value && !poll.value.closedAt);
    const isDateFinder = computed(() => !!poll.value && poll.value.type === 'date_finder');
    const options = computed(() => (poll.value ? sortOptions(h.options(poll.value.id)) : []));

    // Only answers from the current audience count (a follow-up poll drops people who declined).
    const respondents = computed(() => (poll.value ? Array.from(h.respondents(poll.value.id)).filter((id) => audience.value.includes(id)) : []));
    const rows = computed(() => {
      const others = respondents.value
        .filter((id) => id !== user.value.id)
        .sort((a, b) => nameOf(a).localeCompare(nameOf(b)));

      return [user.value.id, ...others];
    });
    const missing = computed(() => audience.value.filter((id) => !respondents.value.includes(id)));
    const totals = computed(() => Object.fromEntries(options.value.map((option) => [option.id, tally(option.id, audience.value)])));

    // poll-rules: most yes answers win; a tie on yes is broken by the fewest no answers (F-06)
    const ranking = computed(() => {
      if (!isDateFinder.value || !isOpen.value) {
        return { ids: [], tieBroken: false };
      }

      const maxYes = Math.max(0, ...options.value.map((option) => totals.value[option.id].yes));

      if (maxYes === 0) {
        return { ids: [], tieBroken: false };
      }

      const tied = options.value.filter((option) => totals.value[option.id].yes === maxYes);
      const minNo = Math.min(...tied.map((option) => totals.value[option.id].no));
      const best = tied.filter((option) => totals.value[option.id].no === minNo);

      return { ids: best.map((option) => option.id), tieBroken: best.length < tied.length };
    });
    const bestIds = computed(() => ranking.value.ids);
    const tieBroken = computed(() => ranking.value.tieBroken);

    const fixedEvents = computed(() => {
      const ids = options.value.map((option) => option.id);

      return data.events.filter((entry) => ids.includes(entry.originOptionId)).sort((a, b) => a.start.localeCompare(b.start));
    });

    const status = computed(() => {
      if (isOpen.value) {
        return { label: t('group.polls.open'), cls: 'badge--primary' };
      }

      if (options.value.some((option) => option.fixed)) {
        return { label: t('group.polls.fixed'), cls: 'badge--success' };
      }

      return { label: t('group.polls.closed'), cls: '' };
    });

    const featureId = computed(() => {
      if (poll.value && poll.value.eventId) {
        return 'F-12';
      }

      return isDateFinder.value ? 'F-06' : 'F-07';
    });

    function answer(optionId, value) {
      if (!isOpen.value) {
        toast(t('poll.closedNote'));

        return;
      }

      const existing = data.responses.find((entry) => entry.optionId === optionId && entry.accountId === user.value.id);

      if (existing) {
        existing.answer = value;

        return;
      }

      data.responses.push({ optionId, accountId: user.value.id, answer: value });
    }

    function closePoll() {
      poll.value.closedAt = TODAY_ISO;
      toast(t('poll.closed.toast'));
    }

    function reopenPoll() {
      poll.value.closedAt = null;
      toast(t('poll.reopened.toast'));
    }

    function back() {
      if (event.value) {
        go('event', { currentEventId: event.value.id });

        return;
      }

      go('group', { currentGroupId: group.value.id });
    }

    // edit-mode: the poll edit page returns here on save and cancel (section 6, "Poll edit")
    function edit() {
      goCreate('new-poll', { editPollId: poll.value.id });
    }

    return {
      ui, user, poll, group, event, audience, visible, canEdit, isOpen, isDateFinder, options, respondents, rows, missing, totals, bestIds, tieBroken,
      fixedEvents, status, featureId, fixOpen, ANSWERS,
      answer, answerOf, closePoll, reopenPoll, back, edit, nameOf, optionTime,
      go, h, t, formatDate, weekdayShort, dayNumber, monthShort
    };
  },
  template: `
    <main class="container page" v-if="visible">
      <div class="poll-head" :data-feature="featureId">
        <button type="button" class="back" @click="back"><Icon name="back" /> {{ t('poll.back', { name: event ? event.title : group.name }) }}</button>
        <div class="poll-head__top">
          <div class="poll-head__title">
            <h1>{{ poll.title }}</h1>
            <div class="poll-head__meta">
              <span :class="['badge', status.cls]">{{ status.label }}</span>
              <span class="badge badge--outline">{{ isDateFinder ? t('group.polls.dateFinder') : t('group.polls.attendance') }}</span>
              <span class="badge badge--outline">{{ poll.mode === 'slots' ? t('newPoll.mode.slots') : t('newPoll.mode.day') }}</span>
              <button type="button" class="poll-head__link" @click="go('group', { currentGroupId: group.id })"><Icon name="users" class="icon-sm" /> {{ group.name }}</button>
              <span class="poll-head__by"><Avatar :id="poll.organizerId" size="sm" /> {{ t('poll.by', { name: nameOf(poll.organizerId) }) }}</span>
            </div>
          </div>
          <div class="poll-actions" v-if="canEdit" data-feature="F-08">
            <button type="button" class="btn btn--primary" v-if="isDateFinder && isOpen" @click="fixOpen = true" data-feature="F-09"><Icon name="sparkle" /> {{ t('poll.actions.fix') }}</button>
            <button type="button" class="btn btn--secondary" v-if="!isDateFinder && isOpen" @click="closePoll">{{ t('poll.actions.close') }}</button>
            <button type="button" class="btn btn--secondary" v-if="!isOpen" @click="reopenPoll">{{ t('poll.actions.reopen') }}</button>
            <button type="button" class="btn btn--ghost" @click="edit"><Icon name="settings" /> {{ t('poll.edit.title') }}</button>
          </div>
        </div>

        <div class="audience" v-if="event" data-feature="F-12">
          <Icon name="users" class="icon-lg" />
          <span class="audience__text">
            <b>{{ t('newPoll.audienceEvent', { event: event.title }) }}</b>
            <span>{{ t('newPoll.audienceEventCount', { n: audience.length }) }}</span>
          </span>
          <button type="button" class="btn btn--soft btn--sm" @click="go('event', { currentEventId: event.id })">{{ t('poll.openEvent') }} <Icon name="chevron" /></button>
        </div>

        <div class="alert" role="status" v-if="!isOpen" data-feature="F-08">
          <Icon name="check" />
          <span>{{ fixedEvents.length ? t('poll.closedFixedNote') : t('poll.closedNote') }}</span>
        </div>
        <p class="notice" v-else-if="!isDateFinder" data-feature="F-07">{{ t('poll.attendanceHint') }}</p>
      </div>

      <section class="section" aria-labelledby="grid-title" :data-feature="isDateFinder ? 'F-06' : 'F-07'">
        <div class="section-head">
          <h2 id="grid-title">{{ t('poll.grid') }}<span class="section-head__count" v-if="respondents.length">{{ respondents.length }}</span></h2>
          <span class="notice">{{ t('group.polls.responses', { n: respondents.length, total: audience.length }) }}</span>
        </div>

        <div class="poll-grid-wrap" tabindex="0" role="region" :aria-label="t('poll.grid')">
          <table class="poll-grid">
            <thead>
              <tr>
                <th scope="col" class="poll-grid__who"><span class="visually-hidden">{{ t('poll.who') }}</span></th>
                <th scope="col" v-for="option in options" :key="option.id" :class="['poll-col', bestIds.includes(option.id) ? 'poll-col--best' : '', option.fixed ? 'poll-col--fixed' : '']">
                  <span class="poll-col__flag" v-if="option.fixed"><span class="tag">{{ t('group.polls.fixed') }}</span></span>
                  <span class="poll-col__flag poll-col__flag--best" v-else-if="bestIds.includes(option.id)" data-feature="F-06">{{ t('poll.best') }}</span>
                  <span class="poll-col__weekday">{{ weekdayShort(option.date) }}</span>
                  <span class="poll-col__day">{{ dayNumber(option.date) }}</span>
                  <span class="poll-col__month">{{ monthShort(option.date) }}</span>
                  <span class="poll-col__time" v-if="option.start">{{ optionTime(option) }}</span>
                  <span class="poll-col__label" v-if="option.label" :title="option.label" :data-feature="isDateFinder ? 'F-06' : 'F-07'">{{ option.label }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="id in rows" :key="id" :class="['poll-grid__row', id === user.id ? 'poll-grid__row--me' : '']">
                <th scope="row" class="poll-grid__who">
                  <span class="poll-grid__person">
                    <Avatar :id="id" size="sm" />
                    <span class="poll-grid__name">{{ nameOf(id) }} <span class="soft" v-if="id === user.id">({{ t('group.member.you') }})</span></span>
                  </span>
                </th>
                <td v-for="option in options" :key="option.id" :class="['poll-cell', bestIds.includes(option.id) ? 'poll-cell--best' : '', option.fixed ? 'poll-cell--fixed' : '']">
                  <div class="answer" v-if="id === user.id && isOpen" role="radiogroup" :aria-label="t('poll.answerLabel', { option: formatDate(option.date, 'weekday') + (option.start ? ' ' + optionTime(option) : '') + (option.label ? ', ' + option.label : '') })">
                    <label class="answer__opt" v-for="value in ANSWERS" :key="value">
                      <input type="radio" :name="'answer-' + option.id" :value="value" :checked="answerOf(option.id, id) === value" @change="answer(option.id, value)">
                      <Mark :answer="value" />
                      <span class="visually-hidden">{{ t('poll.answer.' + value) }}</span>
                    </label>
                  </div>
                  <template v-else>
                    <Mark :answer="answerOf(option.id, id)" />
                    <span class="visually-hidden">{{ t('poll.answer.' + answerOf(option.id, id)) }}</span>
                  </template>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <th scope="row" class="poll-grid__who poll-grid__totals-label">{{ t('poll.totals') }}</th>
                <td v-for="option in options" :key="option.id" :class="['poll-total', bestIds.includes(option.id) ? 'poll-cell--best' : '', option.fixed ? 'poll-cell--fixed' : '']">
                  <span class="poll-total__yes"><Mark answer="yes" /> {{ totals[option.id].yes }}</span>
                  <span class="poll-total__maybe" v-if="totals[option.id].maybe"><Mark answer="maybe" /> {{ totals[option.id].maybe }}</span>
                  <span class="poll-total__no" v-if="tieBroken" data-feature="F-06"><Mark answer="no" /> {{ totals[option.id].no }}</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <EmptyState v-if="!respondents.length" :title="t('poll.empty.title')" :text="t('poll.empty.text')" />
        <div class="poll-missing" v-else-if="missing.length">
          <span class="poll-missing__label">{{ t('poll.missing') }}</span>
          <AvatarStack :ids="missing" :max="6" />
          <span class="poll-missing__names">{{ missing.map(nameOf).join(', ') }}</span>
        </div>
      </section>

      <section class="section" aria-labelledby="fixed-title" v-if="fixedEvents.length" data-feature="F-09">
        <div class="section-head">
          <h2 id="fixed-title">{{ t('poll.events') }}<span class="section-head__count">{{ fixedEvents.length }}</span></h2>
        </div>
        <div class="stack">
          <button type="button" class="card card--interactive event-row" v-for="entry in fixedEvents" :key="entry.id" @click="go('event', { currentEventId: entry.id })">
            <DateBlock :date="entry.start" fixed />
            <span class="event-row__main">
              <span class="event-row__title">{{ entry.title }}</span>
              <span class="event-row__meta">
                <span>{{ formatDate(entry.start, 'weekday') }}<template v-if="entry.startTime">, {{ entry.startTime }}</template></span>
                <span>{{ t('group.events.accepted', { n: h.participants(entry.id, 'accepted').length }) }}</span>
                <span v-if="h.participants(entry.id, 'pending').length">{{ t('group.events.pending', { n: h.participants(entry.id, 'pending').length }) }}</span>
              </span>
            </span>
            <span class="event-row__aside"><Icon name="chevron" class="icon-md icon-soft" /></span>
          </button>
        </div>
      </section>

      <PollFixDialog v-if="fixOpen" :poll="poll" :options="options" :audience="audience" :totals="totals" :best="bestIds" @close="fixOpen = false" />
    </main>
    <main class="container page" v-else>
      <div class="stack">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('group.notFound') }}</h1>
      </div>
    </main>`
};

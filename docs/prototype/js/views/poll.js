// Poll page (F-06 to F-09, F-12): header, response grid with best options and totals,
// fixing dates, closing and reopening, settings.

import { ref, reactive, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, viewState, toast, nextId } from '../core.js';
import { t, formatDate, weekdayShort, dayNumber, monthShort } from '../i18n.js';

const TODAY_ISO = '2026-10-25';
const ANSWERS = ['yes', 'maybe', 'no'];

// Review bar states that need a specific poll from the mock data; default follows navigation.
const statePolls = { closed: 'p7', noResponses: 'p8', notInAudience: 'p9', fixed: 'p4', followUp: 'p2' };

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

function responseCount(optionId) {
  return data.responses.filter((entry) => entry.optionId === optionId).length;
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
              <span class="fix-option__counts">{{ t('poll.totals.yes', { n: totals[option.id].yes }) }}, {{ t('poll.totals.maybe', { n: totals[option.id].maybe }) }}</span>
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

const PollSettingsDialog = {
  props: { poll: { type: Object, required: true } },
  emits: ['close', 'deleted'],
  setup(props, { emit }) {
    const title = ref(props.poll.title);
    const titleError = ref('');
    const optionError = ref('');
    const removing = ref(null);
    const deleting = ref(false);
    const isSlots = props.poll.mode === 'slots';
    const newOption = reactive({ date: '', start: isSlots ? '19:30' : null, end: isSlots ? '22:00' : null, label: '' });

    const options = computed(() => sortOptions(h.options(props.poll.id)));
    const locked = computed(() => !!props.poll.closedAt);
    const totalResponses = computed(() => options.value.reduce((sum, option) => sum + responseCount(option.id), 0));

    function save() {
      if (!title.value.trim()) {
        titleError.value = t('newPoll.titleRequired');

        return;
      }

      props.poll.title = title.value.trim();
      toast(t('poll.settings.saved'));
      emit('close');
    }

    function addOption() {
      if (!newOption.date) {
        optionError.value = t('poll.settings.dateRequired');

        return;
      }

      if (isSlots && (!newOption.start || !newOption.end)) {
        optionError.value = t('poll.settings.timeRequired');

        return;
      }

      data.pollOptions.push({
        id: nextId('o'),
        pollId: props.poll.id,
        date: newOption.date,
        start: isSlots ? newOption.start : null,
        end: isSlots ? newOption.end : null,
        label: isSlots ? newOption.label.trim() : '',
        fixed: false
      });
      optionError.value = '';
      newOption.date = '';
      newOption.label = '';
      toast(t('poll.settings.added'));
    }

    function removeOption(option) {
      data.responses = data.responses.filter((entry) => entry.optionId !== option.id);
      data.pollOptions = data.pollOptions.filter((entry) => entry.id !== option.id);
      removing.value = null;
      toast(t('poll.settings.removed'));
    }

    function deletePoll() {
      const ids = options.value.map((option) => option.id);
      data.responses = data.responses.filter((entry) => !ids.includes(entry.optionId));
      data.pollOptions = data.pollOptions.filter((entry) => entry.pollId !== props.poll.id);
      data.polls = data.polls.filter((entry) => entry.id !== props.poll.id);
      toast(t('poll.settings.deleted'));
      emit('deleted');
    }

    return {
      title, titleError, optionError, removing, deleting, isSlots, newOption, options, locked, totalResponses,
      save, addOption, removeOption, deletePoll, responseCount, optionTime, t, formatDate
    };
  },
  template: `
    <AppDialog :title="t('poll.settings.title')" :subtitle="poll.title" @close="$emit('close')">
      <form id="poll-settings-form" class="form-grid" @submit.prevent="save" novalidate>
        <FormField :label="t('poll.settings.name')" control="poll-settings-title" required :error="titleError">
          <input class="input" id="poll-settings-title" v-model="title" :aria-invalid="titleError ? 'true' : null">
        </FormField>
      </form>

      <FormField :label="t('poll.settings.options')" group :hint="locked ? t('poll.settings.optionsLocked') : ''" :error="optionError">
        <div class="options">
          <ul class="options__rows">
            <li class="settings-option" v-for="option in options" :key="option.id">
              <span class="settings-option__main">
                <span class="settings-option__date">{{ formatDate(option.date, 'weekday') }}<template v-if="option.start">, {{ optionTime(option) }}</template><template v-if="option.label"> · {{ option.label }}</template></span>
                <span class="settings-option__meta">
                  <span>{{ t('poll.settings.answers', { n: responseCount(option.id) }) }}</span>
                  <span class="badge badge--success" v-if="option.fixed">{{ t('group.polls.fixed') }}</span>
                </span>
              </span>
              <span class="settings-option__confirm" v-if="removing === option.id">
                <span class="small">{{ t('poll.settings.removeConfirm', { n: responseCount(option.id) }) }}</span>
                <button type="button" class="btn btn--danger btn--sm" @click="removeOption(option)">{{ t('poll.settings.remove') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" @click="removing = null">{{ t('poll.settings.keep') }}</button>
              </span>
              <button v-else type="button" class="btn btn--ghost btn--icon btn--sm" :disabled="locked || option.fixed" :aria-label="t('newPoll.removeOption')" @click="removing = option.id"><Icon name="minus" /></button>
            </li>
          </ul>
          <div :class="['option-row', isSlots ? 'option-row--slot' : '']" v-if="!locked">
            <input class="input" type="date" v-model="newOption.date" :aria-label="t('newEvent.date')" :aria-invalid="optionError ? 'true' : null">
            <template v-if="isSlots">
              <input class="input" type="time" v-model="newOption.start" :aria-label="t('newPoll.start')">
              <input class="input" type="time" v-model="newOption.end" :aria-label="t('newPoll.end')">
              <input class="input input--label" type="text" v-model="newOption.label" :placeholder="t('newPoll.slotLabelPlaceholder')" :aria-label="t('newPoll.slotLabel')">
            </template>
            <button type="button" class="btn btn--soft btn--icon btn--sm" :aria-label="t('newPoll.addOption')" @click="addOption"><Icon name="plus" /></button>
          </div>
        </div>
      </FormField>

      <div class="settings-danger">
        <template v-if="deleting">
          <p class="small">{{ t('poll.settings.deleteConfirm', { title: poll.title, n: totalResponses }) }}</p>
          <div class="row">
            <button type="button" class="btn btn--danger btn--sm" @click="deletePoll">{{ t('poll.settings.delete') }}</button>
            <button type="button" class="btn btn--ghost btn--sm" @click="deleting = false">{{ t('dialog.cancel') }}</button>
          </div>
        </template>
        <button v-else type="button" class="btn btn--danger btn--sm" @click="deleting = true">{{ t('poll.settings.delete') }}</button>
      </div>

      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="poll-settings-form" class="btn btn--primary">{{ t('poll.settings.save') }}</button>
      </template>
    </AppDialog>`
};

export const PollView = {
  components: { PollFixDialog, PollSettingsDialog },
  setup() {
    const state = viewState('poll');
    const user = currentUser;
    const fixOpen = ref(false);
    const settingsOpen = ref(false);

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

    const bestIds = computed(() => {
      if (!isDateFinder.value || !isOpen.value) {
        return [];
      }

      const max = Math.max(0, ...options.value.map((option) => totals.value[option.id].yes));

      if (max === 0) {
        return [];
      }

      return options.value.filter((option) => totals.value[option.id].yes === max).map((option) => option.id);
    });

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

    function afterDelete() {
      settingsOpen.value = false;
      go('group', { currentGroupId: group.value ? group.value.id : ui.currentGroupId });
    }

    return {
      ui, user, poll, group, event, audience, visible, canEdit, isOpen, isDateFinder, options, respondents, rows, missing, totals, bestIds,
      fixedEvents, status, featureId, fixOpen, settingsOpen, ANSWERS,
      answer, answerOf, closePoll, reopenPoll, back, afterDelete, nameOf, optionTime,
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
            <button type="button" class="btn btn--ghost" @click="settingsOpen = true"><Icon name="settings" /> {{ t('poll.actions.settings') }}</button>
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
                  <span class="poll-col__label" v-if="option.label">{{ option.label }}</span>
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
                  <div class="answer" v-if="id === user.id && isOpen" role="radiogroup" :aria-label="t('poll.answerLabel', { option: formatDate(option.date, 'weekday') + (option.start ? ' ' + optionTime(option) : '') })">
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
      <PollSettingsDialog v-if="settingsOpen" :poll="poll" @close="settingsOpen = false" @deleted="afterDelete" />
    </main>
    <main class="container page" v-else>
      <div class="stack">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('group.notFound') }}</h1>
      </div>
    </main>`
};

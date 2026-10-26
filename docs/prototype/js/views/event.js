// Event page (F-10 to F-13): key details, participation with accept and decline, lists,
// follow-up polls and child events. Review-bar states override the perspective on the
// current event (past, no acceptances, declined) so every state is reachable from any event.

import { ref, reactive, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, go, goCreate, viewState, toast } from '../core.js';
import { t, formatDate, relativeDay, isPast } from '../i18n.js';
import { CommentThread } from './list.js'; // poll-rules

function personName(id) {
  return h.displayName(id) || h.account(id).email;
}

function claimLabel(entry) {
  if (entry.item.quantity === null) {
    return t('event.task');
  }

  return `${entry.claim.quantity}${entry.item.unit ? ' ' + entry.item.unit : ''}`;
}

function claimsInEvent(eventId, accountId) {
  const listIds = h.listsOfEvent(eventId).map((list) => list.id);

  return data.claims
    .filter((claim) => claim.accountId === accountId)
    .map((claim) => ({ claim, item: data.items.find((item) => item.id === claim.itemId) }))
    .filter((entry) => entry.item && listIds.includes(entry.item.listId))
    .map((entry) => ({ ...entry, list: h.list(entry.item.listId) }));
}

function setStatus(eventId, accountId, status) {
  const entry = data.participations.find((item) => item.eventId === eventId && item.accountId === accountId);

  if (entry) {
    entry.status = status;

    return;
  }

  data.participations.push({ eventId, accountId, status });
}

function deleteList(listId) {
  const itemIds = h.items(listId).map((item) => item.id);
  data.claims = data.claims.filter((claim) => !itemIds.includes(claim.itemId));
  data.items = data.items.filter((item) => item.listId !== listId);
  data.lists = data.lists.filter((list) => list.id !== listId);
}

function deletePoll(pollId) {
  const optionIds = h.options(pollId).map((option) => option.id);
  data.responses = data.responses.filter((response) => !optionIds.includes(response.optionId));
  data.pollOptions = data.pollOptions.filter((option) => option.pollId !== pollId);
  data.polls = data.polls.filter((poll) => poll.id !== pollId);
}

// Deleting removes lists, follow-up polls and child events with everything attached (F-10).
function deleteEvent(eventId) {
  data.events.filter((event) => event.parentEventId === eventId).forEach((child) => deleteEvent(child.id));
  h.listsOfEvent(eventId).forEach((list) => deleteList(list.id));
  data.polls.filter((poll) => poll.eventId === eventId).forEach((poll) => deletePoll(poll.id));
  data.participations = data.participations.filter((entry) => entry.eventId !== eventId);
  data.comments = data.comments.filter((comment) => comment.eventId !== eventId); // poll-rules
  data.events = data.events.filter((event) => event.id !== eventId);
}

const EventEditDialog = {
  props: { event: { type: Object, required: true } },
  emits: ['close', 'save'],
  setup(props, { emit }) {
    const form = reactive({
      title: props.event.title,
      date: props.event.start,
      time: props.event.startTime || '',
      end: props.event.end || '',
      location: props.event.location || '',
      description: props.event.description || ''
    });
    const errors = reactive({ title: '', date: '' });
    const moved = computed(() => form.date !== props.event.start || (form.time || null) !== props.event.startTime);

    function save() {
      errors.title = form.title.trim() ? '' : t('newEvent.titleRequired');
      errors.date = form.date ? '' : t('newEvent.dateRequired');

      if (errors.title || errors.date) {
        return;
      }

      emit('save', { ...form });
    }

    return { form, errors, moved, save, t };
  },
  template: `
    <AppDialog :title="t('event.edit.title')" @close="$emit('close')">
      <form id="edit-event-form" @submit.prevent="save" class="form-grid" novalidate data-feature="F-10">
        <FormField :label="t('newEvent.name')" control="edit-event-title" required :error="errors.title">
          <input class="input" id="edit-event-title" v-model="form.title" :aria-invalid="errors.title ? 'true' : null">
        </FormField>
        <div class="form-grid form-grid--2">
          <FormField :label="t('newEvent.date')" control="edit-event-date" required :error="errors.date" :hint="t('event.edit.dateHint')">
            <input class="input" id="edit-event-date" type="date" v-model="form.date" :aria-invalid="errors.date ? 'true' : null">
          </FormField>
          <FormField :label="t('newEvent.time')" control="edit-event-time" :hint="t('newEvent.timeHint')">
            <input class="input" id="edit-event-time" type="time" v-model="form.time">
          </FormField>
        </div>
        <FormField :label="t('newEvent.end')" control="edit-event-end" :hint="t('newEvent.timeHint')">
          <input class="input input--date" id="edit-event-end" type="time" v-model="form.end">
        </FormField>
        <FormField :label="t('newEvent.location')" control="edit-event-location">
          <input class="input" id="edit-event-location" v-model="form.location" :placeholder="t('newEvent.locationPlaceholder')">
        </FormField>
        <FormField :label="t('newEvent.description')" control="edit-event-description">
          <textarea class="textarea" id="edit-event-description" v-model="form.description" :placeholder="t('newEvent.descriptionPlaceholder')"></textarea>
        </FormField>
        <div class="alert alert--warning" role="status" v-if="moved">
          <Icon name="question" />
          <span>{{ t('event.edit.moveWarning') }}</span>
        </div>
      </form>
      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="edit-event-form" class="btn btn--primary">{{ t('event.edit.save') }}</button>
      </template>
    </AppDialog>`
};

const DeclineDialog = {
  props: { claims: { type: Array, required: true } },
  emits: ['close', 'confirm'],
  setup() {
    return { claimLabel, t };
  },
  template: `
    <AppDialog :title="t('event.decline.title')" :subtitle="t('event.decline.text')" @close="$emit('close')">
      <ul class="rows" data-feature="F-11">
        <li class="row-item" v-for="entry in claims" :key="entry.item.id">
          <span class="claim__qty">{{ claimLabel(entry) }}</span>
          <span class="row-item__main">
            <span class="row-item__title">{{ entry.item.name }}</span>
            <span class="row-item__meta">{{ t('event.inList', { list: entry.list.title }) }}</span>
          </span>
        </li>
      </ul>
      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="button" class="btn btn--danger" @click="$emit('confirm')">{{ t('event.decline.confirm') }}</button>
      </template>
    </AppDialog>`
};

const DeleteEventDialog = {
  props: { counts: { type: Object, required: true } },
  emits: ['close', 'confirm'],
  setup() {
    return { t };
  },
  template: `
    <AppDialog :title="t('event.delete.title')" :subtitle="t('event.delete.text')" @close="$emit('close')">
      <div class="row" data-feature="F-10">
        <span class="badge">{{ t('event.delete.lists', { n: counts.lists }) }}</span>
        <span class="badge">{{ t('event.delete.polls', { n: counts.polls }) }}</span>
        <span class="badge">{{ t('event.delete.children', { n: counts.children }) }}</span>
      </div>
      <template #foot>
        <button type="button" class="btn btn--secondary" @click="$emit('close')">{{ t('dialog.cancel') }}</button>
        <button type="button" class="btn btn--danger" @click="$emit('confirm')">{{ t('event.delete.confirm') }}</button>
      </template>
    </AppDialog>`
};

export const EventView = {
  components: { EventEditDialog, DeclineDialog, DeleteEventDialog, CommentThread },
  setup() {
    const state = viewState('event');
    const user = currentUser;
    const dialog = ref(null);
    const pendingClaims = ref([]);

    const event = computed(() => h.event(ui.currentEventId));
    const group = computed(() => (event.value ? h.group(event.value.groupId) : null));
    // No access shows "Not found" so the event's existence stays hidden (section 8).
    const visible = computed(() => !!group.value && h.membersOf(group.value.id).includes(user.value.id));
    const past = computed(() => !!event.value && (state.value === 'past' || isPast(event.value.start)));
    const isOrganizer = computed(() => !!event.value && event.value.organizerId === user.value.id);
    const canManage = computed(() => !!event.value && (isOrganizer.value || h.canManage(event.value.groupId, user.value.id)));
    // Members set the status for others only when the group option allows it; managers always can (F-11, F-03).
    const canSetStatus = computed(() => canManage.value || (!!group.value && !!group.value.membersCanSetStatus));
    // new-group: the per-person controls appear only after "Manage", so the default card stays read-only (F-11).
    const managing = ref(false);
    // Child events have no follow-up polls and no children of their own (F-12, one level only).
    const isChild = computed(() => !!event.value && !!event.value.parentEventId);

    const participations = computed(() => {
      if (!event.value) {
        return [];
      }

      let entries = h.participants(event.value.id).map((entry) => ({ ...entry }));

      if (state.value === 'noAcceptances') {
        entries = entries.map((entry) => (entry.status === 'accepted' ? { ...entry, status: 'pending' } : entry));
      }

      if (state.value === 'declined') {
        entries = entries.filter((entry) => entry.accountId !== user.value.id);
        entries.push({ eventId: event.value.id, accountId: user.value.id, status: 'declined' });
      }

      return entries;
    });

    const byStatus = (status) => participations.value.filter((entry) => entry.status === status).map((entry) => entry.accountId);
    const columns = computed(() => [
      { status: 'accepted', label: t('event.accepted'), ids: byStatus('accepted') },
      { status: 'pending', label: t('event.pending'), ids: byStatus('pending') },
      { status: 'declined', label: t('event.declined'), ids: byStatus('declined') }
    ]);
    const acceptedCount = computed(() => byStatus('accepted').length);

    const myStatus = computed(() => {
      const entry = participations.value.find((item) => item.accountId === user.value.id);

      return entry ? entry.status : null;
    });
    const isParticipant = computed(() => ['accepted', 'pending'].includes(myStatus.value));

    const myBadge = computed(() => {
      if (myStatus.value === 'accepted') {
        return { label: t('group.events.youAccepted'), cls: 'badge--success' };
      }

      if (myStatus.value === 'declined') {
        return { label: t('group.events.youDeclined'), cls: 'badge--danger' };
      }

      return { label: t('group.events.youPending'), cls: 'badge--warning' };
    });

    const parent = computed(() => (event.value && event.value.parentEventId ? h.event(event.value.parentEventId) : null));
    const originPoll = computed(() => {
      if (!event.value || !event.value.originOptionId) {
        return null;
      }

      const option = data.pollOptions.find((item) => item.id === event.value.originOptionId);

      return option ? data.polls.find((poll) => poll.id === option.pollId) : null;
    });

    const timeLabel = computed(() => {
      if (!event.value || !event.value.startTime) {
        return '';
      }

      if (!event.value.end) {
        return event.value.startTime;
      }

      return t('event.timeRange', { start: event.value.startTime, end: event.value.end });
    });

    const lists = computed(() => (event.value ? h.listsOfEvent(event.value.id) : []));
    const followUps = computed(() => {
      if (!event.value) {
        return [];
      }

      return data.polls
        .filter((poll) => poll.eventId === event.value.id)
        .sort((a, b) => (a.closedAt ? 1 : 0) - (b.closedAt ? 1 : 0) || b.createdAt.localeCompare(a.createdAt));
    });
    const children = computed(() => {
      if (!event.value) {
        return [];
      }

      return data.events.filter((item) => item.parentEventId === event.value.id).sort((a, b) => a.start.localeCompare(b.start));
    });
    const deleteCounts = computed(() => ({ lists: lists.value.length, polls: followUps.value.length, children: children.value.length }));

    // poll-rules: one collapsed thread per event (F-20); group owner and admins moderate
    const commentsOpen = ref(false);
    const commentCount = computed(() => (event.value ? data.comments.filter((comment) => comment.eventId === event.value.id).length : 0));
    const canModerateComments = computed(() => !!event.value && h.canManage(event.value.groupId, user.value.id));

    function pollMeta(poll) {
      const audience = h.audience(poll);
      const answered = Array.from(h.respondents(poll.id)).filter((id) => audience.includes(id)).length;

      return { total: audience.length, answered, options: h.options(poll.id).length };
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

    function childStatus(eventId) {
      const status = h.participation(eventId, user.value.id);

      if (status === 'accepted') {
        return { label: t('group.events.youAccepted'), cls: 'badge--success' };
      }

      if (status === 'declined') {
        return { label: t('group.events.youDeclined'), cls: 'badge--danger' };
      }

      return { label: t('group.events.youPending'), cls: 'badge--warning' };
    }

    // Every write leaves the simulated review state, otherwise the override would hide the result.
    function leaveState() {
      state.value = 'default';
    }

    function accept() {
      if (past.value) {
        return;
      }

      setStatus(event.value.id, user.value.id, 'accepted');
      leaveState();
      toast(t('event.acceptedToast'));
    }

    function decline() {
      if (past.value || myStatus.value === 'declined') {
        return;
      }

      pendingClaims.value = claimsInEvent(event.value.id, user.value.id);

      if (pendingClaims.value.length) {
        dialog.value = 'decline';

        return;
      }

      finishDecline();
    }

    function confirmDecline() {
      const itemIds = pendingClaims.value.map((entry) => entry.item.id);
      data.claims = data.claims.filter((claim) => !(claim.accountId === user.value.id && itemIds.includes(claim.itemId)));
      finishDecline();
    }

    function finishDecline() {
      setStatus(event.value.id, user.value.id, 'declined');
      dialog.value = null;
      leaveState();
      toast(t('event.declinedToast'));
    }

    function changeStatus(accountId, status) {
      setStatus(event.value.id, accountId, status);
      leaveState();
      toast(t('event.statusChanged', { name: personName(accountId) }));
    }

    function removeParticipant(accountId) {
      data.participations = data.participations.filter((entry) => !(entry.eventId === event.value.id && entry.accountId === accountId));
      leaveState();
      toast(t('event.removed', { name: personName(accountId) }));
    }

    function saveEdit(form) {
      const moved = form.date !== event.value.start || (form.time || null) !== event.value.startTime;

      Object.assign(event.value, {
        title: form.title.trim(),
        description: form.description.trim(),
        location: form.location.trim(),
        start: form.date,
        startTime: form.time || null,
        end: form.end || null
      });

      if (moved) {
        data.participations
          .filter((entry) => entry.eventId === event.value.id && entry.status === 'accepted')
          .forEach((entry) => { entry.status = 'pending'; });
      }

      dialog.value = null;
      toast(moved ? t('event.edit.reset') : t('event.edit.saved'));
    }

    function confirmDelete() {
      const groupId = event.value.groupId;
      deleteEvent(event.value.id);
      dialog.value = null;
      toast(t('event.deleted'));
      go('group', { currentGroupId: groupId });
    }

    return {
      ui, state, user, dialog, pendingClaims, event, group, visible, past, canManage, canSetStatus, managing, isChild, columns, acceptedCount, myStatus, isParticipant, myBadge,
      parent, originPoll, timeLabel, lists, followUps, children, deleteCounts, pollMeta, pollStatus, childStatus, personName,
      accept, decline, confirmDecline, changeStatus, removeParticipant, saveEdit, confirmDelete, go, goCreate, h, t, formatDate, relativeDay,
      commentsOpen, commentCount, canModerateComments // poll-rules
    };
  },
  template: `
    <main class="container page" v-if="event && visible">
      <div class="event-head" data-feature="F-10">
        <button type="button" class="back" @click="go('group', { currentGroupId: group.id })"><Icon name="back" /> {{ t('event.back', { group: group.name }) }}</button>
        <div class="event-head__top">
          <DateBlock :date="event.start" :fixed="!!event.originOptionId" />
          <div class="event-head__title">
            <div class="event-head__badges">
              <span class="badge" v-if="past">{{ t('event.past') }}</span>
              <span class="badge badge--primary" v-else>{{ relativeDay(event.start) }}</span>
              <button type="button" class="badge badge--outline badge--button" v-if="originPoll" @click="go('poll', { currentPollId: originPoll.id })" data-feature="F-09"><Icon name="poll" class="icon-sm" /> {{ t('group.events.fromPoll') }}</button>
              <button type="button" class="badge badge--outline badge--button" v-if="parent" @click="go('event', { currentEventId: parent.id })" data-feature="F-12"><Icon name="calendar" class="icon-sm" /> {{ t('event.parent', { event: parent.title }) }}</button>
            </div>
            <h1>{{ event.title }}</h1>
            <div class="event-meta">
              <span><Icon name="calendar" class="icon-sm" /> {{ formatDate(event.start, 'weekday') }}</span>
              <span v-if="timeLabel"><Icon name="clock" class="icon-sm" /> {{ timeLabel }}</span>
              <span v-if="event.location"><Icon name="pin" class="icon-sm" /> {{ event.location }}</span>
              <button type="button" class="event-meta__link" @click="go('group', { currentGroupId: group.id })"><Icon name="users" class="icon-sm" /> {{ group.name }}</button>
              <span>{{ t('event.organizer', { name: personName(event.organizerId) }) }}</span>
            </div>
            <p class="lede" v-if="event.description">{{ event.description }}</p>
          </div>
          <div class="event-actions" v-if="canManage">
            <button type="button" class="btn btn--secondary btn--sm" @click="dialog = 'edit'"><Icon name="settings" /> {{ t('event.edit') }}</button>
            <button type="button" class="btn btn--danger btn--sm" @click="dialog = 'delete'">{{ t('event.delete') }}</button>
          </div>
        </div>
      </div>

      <section class="card participation" aria-labelledby="participation-title" data-feature="F-11">
        <div class="section-head">
          <h2 id="participation-title">{{ t('event.participation') }}</h2>
          <span class="participation__head-aside">
            <span :class="['badge', myBadge.cls]">{{ myBadge.label }}</span>
            <button type="button" class="btn btn--ghost btn--sm" v-if="canSetStatus" :aria-pressed="managing" @click="managing = !managing" :data-feature="canManage ? null : 'F-03'">{{ managing ? t('event.manageDone') : t('event.manage') }}</button>
          </span>
        </div>

        <div class="participation__own">
          <span class="participation__question">{{ past ? t('event.pastNote') : t('event.yourAnswer') }}</span>
          <div class="participation__buttons" role="group" :aria-label="t('event.yourAnswer')">
            <button type="button" :class="['btn', myStatus === 'accepted' ? 'btn--primary' : 'btn--secondary']" :aria-pressed="myStatus === 'accepted'" :disabled="past" @click="accept"><Icon name="check" /> {{ t('event.accept') }}</button>
            <button type="button" class="btn btn--danger" :aria-pressed="myStatus === 'declined'" :disabled="past" @click="decline"><Icon name="x" /> {{ t('event.decline') }}</button>
          </div>
        </div>

        <div class="alert" v-if="!acceptedCount">
          <Icon name="users" />
          <span><b>{{ t('event.noAcceptances.title') }}</b><br>{{ t('event.noAcceptances.text') }}</span>
        </div>

        <div class="participation__groups">
          <div class="participation__group" v-for="column in columns" :key="column.status">
            <h3>{{ column.label }} <span class="section-head__count">{{ column.ids.length }}</span></h3>
            <ul class="participants" v-if="column.ids.length">
              <li class="participant" v-for="id in column.ids" :key="id">
                <Avatar :id="id" size="sm" />
                <span class="participant__name">{{ personName(id) }} <span class="soft small" v-if="id === user.id">({{ t('group.member.you') }})</span></span>
                <span class="participant__controls" v-if="managing && id !== user.id">
                  <select class="select input--sm participant__status" :value="column.status" :aria-label="t('event.setStatus', { name: personName(id) })" @change="changeStatus(id, $event.target.value)" :data-feature="canManage ? null : 'F-03'">
                    <option value="accepted">{{ t('event.accepted') }}</option>
                    <option value="pending">{{ t('event.pending') }}</option>
                    <option value="declined">{{ t('event.declined') }}</option>
                  </select>
                  <button type="button" class="btn btn--ghost btn--icon btn--sm" v-if="canManage" :aria-label="t('event.remove', { name: personName(id) })" @click="removeParticipant(id)"><Icon name="x" /></button>
                </span>
              </li>
            </ul>
            <p class="soft small" v-else>{{ t('event.none') }}</p>
          </div>
        </div>

        <p class="notice" v-if="!isParticipant">{{ t('event.keyDetailsNote') }}</p>
      </section>

      <template v-if="isParticipant">
        <section class="section" aria-labelledby="event-lists-title" data-feature="F-13">
          <div class="section-head">
            <h2 id="event-lists-title">{{ t('event.lists') }}<span class="section-head__count" v-if="lists.length">{{ lists.length }}</span></h2>
            <button type="button" class="btn btn--secondary btn--sm" @click="goCreate('new-list', { groupId: group.id, eventId: event.id })"><Icon name="plus" /> {{ t('group.new.list') }}</button>
          </div>
          <div class="grid grid--2" v-if="lists.length">
            <button type="button" class="card card--interactive list-card" v-for="list in lists" :key="list.id" @click="go('list', { currentListId: list.id })">
              <span class="list-card__icon"><Icon name="list" /></span>
              <span class="row-item__main">
                <span class="row-item__title">{{ list.title }}</span>
                <span class="row-item__meta">{{ t('dash.lists.claimed', { done: h.listProgress(list.id).done, total: h.listProgress(list.id).total }) }}</span>
                <span class="row row--progress">
                  <ProgressBar :done="h.listProgress(list.id).done" :total="h.listProgress(list.id).total" />
                </span>
              </span>
            </button>
          </div>
          <EmptyState v-else :title="t('event.lists.empty.title')" :text="t('event.lists.empty.text')">
            <button type="button" class="btn btn--secondary" @click="goCreate('new-list', { groupId: group.id, eventId: event.id })"><Icon name="plus" /> {{ t('group.new.list') }}</button>
          </EmptyState>
        </section>

        <section class="section" aria-labelledby="followups-title" v-if="!isChild" data-feature="F-12">
          <div class="section-head">
            <h2 id="followups-title">{{ t('event.followUps') }}<span class="section-head__count" v-if="followUps.length && myStatus === 'accepted'">{{ followUps.length }}</span></h2>
            <button type="button" class="btn btn--primary btn--sm" @click="goCreate('new-poll', { groupId: group.id, eventId: event.id })"><Icon name="poll" /> {{ t('event.followUps.new') }}</button>
          </div>
          <p class="notice" v-if="myStatus !== 'accepted'">{{ t('event.followUps.acceptFirst') }}</p>
          <div class="stack" v-else-if="followUps.length">
            <button type="button" class="card card--interactive poll-row" v-for="poll in followUps" :key="poll.id" @click="go('poll', { currentPollId: poll.id })">
              <span :class="['poll-row__icon', !poll.closedAt ? 'poll-row__icon--open' : '']"><Icon :name="poll.type === 'attendance' ? 'grid' : 'poll'" /></span>
              <span class="poll-row__main">
                <span class="poll-row__title">{{ poll.title }}</span>
                <span class="poll-row__meta">
                  <span>{{ poll.type === 'attendance' ? t('group.polls.attendance') : t('group.polls.dateFinder') }}</span>
                  <span>{{ t('group.polls.options', { n: pollMeta(poll).options }) }}</span>
                </span>
              </span>
              <span class="poll-row__aside">
                <span class="response-count" v-if="!poll.closedAt">
                  <ProgressBar :done="pollMeta(poll).answered" :total="pollMeta(poll).total" />
                  {{ pollMeta(poll).answered }}/{{ pollMeta(poll).total }}
                </span>
                <span :class="['badge', pollStatus(poll).cls]">{{ pollStatus(poll).label }}</span>
              </span>
            </button>
          </div>
          <EmptyState v-else :title="t('event.followUps.empty.title')" :text="t('event.followUps.empty.text')">
            <button type="button" class="btn btn--primary" @click="goCreate('new-poll', { groupId: group.id, eventId: event.id })"><Icon name="plus" /> {{ t('event.followUps.new') }}</button>
          </EmptyState>
        </section>

        <section class="section" aria-labelledby="children-title" v-if="!isChild" data-feature="F-12">
          <div class="section-head">
            <h2 id="children-title">{{ t('event.children') }}<span class="section-head__count" v-if="children.length">{{ children.length }}</span></h2>
          </div>
          <div class="stack" v-if="children.length">
            <button type="button" class="card card--interactive event-row" v-for="child in children" :key="child.id" @click="go('event', { currentEventId: child.id })" data-feature="F-09">
              <DateBlock :date="child.start" :fixed="!!child.originOptionId" />
              <span class="event-row__main">
                <span class="event-row__title">{{ child.title }}</span>
                <span class="event-row__meta">
                  <span>{{ formatDate(child.start, 'weekday') }}<template v-if="child.startTime">, {{ child.startTime }}</template></span>
                  <span v-if="child.location">{{ child.location }}</span>
                </span>
                <span class="event-row__meta">
                  <span>{{ t('group.events.accepted', { n: h.participants(child.id, 'accepted').length }) }}</span>
                  <span v-if="h.participants(child.id, 'pending').length">{{ t('group.events.pending', { n: h.participants(child.id, 'pending').length }) }}</span>
                </span>
              </span>
              <span class="event-row__aside">
                <span :class="['badge', childStatus(child.id).cls]">{{ childStatus(child.id).label }}</span>
              </span>
            </button>
          </div>
          <EmptyState v-else :title="t('event.children.empty.title')" :text="t('event.children.empty.text')" />
        </section>
      </template>

      <!-- poll-rules: everyone who sees the key details sees the thread; collapsed until opened -->
      <section class="card event-comments" aria-labelledby="event-comments-title" data-feature="F-20">
        <h2 id="event-comments-title" class="event-comments__title">
          <button type="button" class="event-comments__toggle" :aria-expanded="commentsOpen" aria-controls="event-comments-body" @click="commentsOpen = !commentsOpen">
            <Icon name="comment" class="icon-md" />
            <span class="event-comments__label">{{ t('event.comments.toggle', { n: commentCount }) }}</span>
            <Icon name="chevron" class="icon-sm icon-soft event-comments__chevron" />
          </button>
        </h2>
        <div class="event-comments__body" id="event-comments-body" v-if="commentsOpen">
          <CommentThread :event-id="event.id" :can-post="true" :can-moderate="canModerateComments" />
        </div>
      </section>

      <EventEditDialog v-if="dialog === 'edit'" :event="event" @close="dialog = null" @save="saveEdit" />
      <DeclineDialog v-if="dialog === 'decline'" :claims="pendingClaims" @close="dialog = null" @confirm="confirmDecline" />
      <DeleteEventDialog v-if="dialog === 'delete'" :counts="deleteCounts" @close="dialog = null" @confirm="confirmDelete" />
    </main>
    <main class="container page" v-else><h1>{{ t('event.notFound') }}</h1></main>`
};

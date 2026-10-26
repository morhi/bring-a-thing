// Create pages for poll, event and list (requirements v1.7, section 6). They read their
// context (group, optional event) from ui.formContext and return to ui.returnView on cancel.
// edit-mode: with editPollId or editListId in the context, the poll and list pages become the
// "Poll edit" and "List edit" pages of section 6 on the same components.

import { ref, reactive, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, toast, nextId, go } from '../core.js';
import { t, TODAY } from '../i18n.js';
import { listGroupId, attendancePollsOf, optionLabel } from './list.js';

const TODAY_ISO = TODAY.toISOString().slice(0, 10);

function cancel() {
  go(ui.returnView || 'dashboard');
}

function finish() {
  go(ui.returnView || 'dashboard');
}

// edit-mode
function responseCount(optionId) {
  return data.responses.filter((entry) => entry.optionId === optionId).length;
}

// edit-mode: shown when the context names a poll or list the current role may not edit (section 8, "No access")
const NotFoundPage = {
  setup() {
    return { go, t };
  },
  template: `
    <main class="container page">
      <div class="stack">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('group.notFound') }}</h1>
      </div>
    </main>`
};

export const CreatePollView = {
  components: { NotFoundPage },
  setup() {
    const props = ui.formContext;
    // edit-mode
    const poll = props.editPollId ? data.polls.find((entry) => entry.id === props.editPollId) : null;
    const editing = !!poll;
    const groupId = editing ? poll.groupId : props.groupId;
    const eventId = editing ? poll.eventId : props.eventId;
    const group = computed(() => h.group(groupId));
    const event = computed(() => (eventId ? h.event(eventId) : null));
    const audienceCount = computed(() => (event.value ? h.participants(event.value.id, 'accepted').length : h.membersOf(groupId).length));
    const allowed = computed(() => {
      if (props.editPollId && !poll) {
        return false;
      }

      if (!editing) {
        return true;
      }

      return poll.organizerId === currentUser.value.id || h.canManage(poll.groupId, currentUser.value.id);
    });

    // edit-mode: the snapshot keeps the option ids, so the save can tell edits, additions and removals apart.
    const original = editing
      ? h.options(poll.id).map((option) => ({ id: option.id, date: option.date, start: option.start, end: option.end, label: option.label, fixed: option.fixed }))
      : [];
    const form = reactive({
      title: editing ? poll.title : '',
      type: editing ? poll.type : 'date_finder',
      mode: editing ? poll.mode : 'day',
      options: original.map((option) => ({ ...option }))
    });
    const errors = reactive({ title: '', options: '' });
    const validOptions = computed(() => form.options.filter((option) => option.date && (form.mode === 'day' || (option.start && option.end))));

    // Switching granularity converts what is there instead of discarding it.
    watch(() => form.mode, (mode) => {
      if (mode === 'day') {
        const seen = new Set();
        form.options = form.options
          .filter((option) => option.date && !seen.has(option.date) && seen.add(option.date))
          .map((option) => ({ date: option.date, start: null, end: null, label: '' }));

        return;
      }

      form.options = form.options.map((option) => ({ date: option.date, start: option.start || '19:30', end: option.end || '22:00', label: option.label || '' }));
    });

    // edit-mode: status, option locks and the removal confirmation (F-08)
    const closed = computed(() => editing && !!poll.closedAt);
    const removing = ref(null);
    const deleting = ref(false);
    const totalResponses = computed(() => original.reduce((sum, option) => sum + responseCount(option.id), 0));
    const status = computed(() => {
      if (!closed.value) {
        return { label: t('group.polls.open'), cls: 'badge--primary' };
      }

      if (original.some((option) => option.fixed)) {
        return { label: t('group.polls.fixed'), cls: 'badge--success' };
      }

      return { label: t('group.polls.closed'), cls: '' };
    });

    function answers(option) {
      return option.id ? responseCount(option.id) : 0;
    }

    // Answers belong to a date and time, so an option with answers keeps both until it is removed.
    function locked(option) {
      return closed.value || option.fixed || answers(option) > 0;
    }

    function removable(option) {
      return !closed.value && !option.fixed;
    }

    function sameSlot(a, b) {
      return a.date === b.date && a.start === b.start && a.end === b.end;
    }

    // The calendar and the rows both go through here. Existing options keep their id through
    // the calendar, so a missing id means a removal, which asks first when answers would go.
    function applyOptions(next) {
      if (!editing) {
        form.options = next;

        return;
      }

      const result = [...next];
      original.forEach((source) => {
        const before = form.options.findIndex((option) => option.id === source.id);

        if (before === -1) {
          return;
        }

        const after = result.find((option) => option.id === source.id);

        if (after) {
          if (locked(source) && !sameSlot(after, source)) {
            Object.assign(after, { date: source.date, start: source.start, end: source.end });
          }

          return;
        }

        const current = form.options[before];

        if (removable(current) && answers(current) === 0) {
          return;
        }

        if (removable(current)) {
          removing.value = current.id;
        }

        result.splice(Math.min(before, result.length), 0, current);
      });
      form.options = result;
    }

    function confirmRemove() {
      form.options = form.options.filter((option) => option.id !== removing.value);
      removing.value = null;
    }

    const removingOption = computed(() => form.options.find((option) => option.id === removing.value) || null);

    function addOption() {
      const last = form.options[form.options.length - 1];
      form.options.push(form.mode === 'slots'
        ? { date: '', start: last ? last.start : '19:30', end: last ? last.end : '22:00', label: '' }
        : { date: '', start: null, end: null, label: '' });
    }

    function removeOption(index) {
      applyOptions(form.options.filter((_, position) => position !== index));
    }

    function optionValues(option) {
      const slots = form.mode === 'slots';

      return {
        date: option.date,
        start: slots ? option.start : null,
        end: slots ? option.end : null,
        label: slots ? (option.label || '').trim() : ''
      };
    }

    function save() {
      errors.title = form.title.trim() ? '' : t('newPoll.titleRequired');
      errors.options = validOptions.value.length ? '' : t('newPoll.optionsRequired');

      if (errors.title || errors.options) {
        return;
      }

      if (editing) {
        update();

        return;
      }

      const created = {
        id: nextId('p'),
        groupId,
        organizerId: currentUser.value.id,
        title: form.title.trim(),
        type: form.type,
        mode: form.mode,
        closedAt: null,
        eventId: eventId || null,
        createdAt: TODAY_ISO
      };
      data.polls.push(created);
      validOptions.value.forEach((option) => {
        data.pollOptions.push({ id: nextId('o'), pollId: created.id, ...optionValues(option), fixed: false });
      });
      finish();
      toast(t('newPoll.created'));
    }

    // edit-mode: a closed poll keeps its options (F-08), so only the title is written then.
    function update() {
      poll.title = form.title.trim();

      if (!closed.value) {
        const keptIds = form.options.filter((option) => option.id).map((option) => option.id);
        const removedIds = original.map((option) => option.id).filter((id) => !keptIds.includes(id));
        data.responses = data.responses.filter((entry) => !removedIds.includes(entry.optionId));
        data.pollOptions = data.pollOptions.filter((entry) => !removedIds.includes(entry.id));
        validOptions.value.forEach((option) => {
          if (option.id) {
            Object.assign(data.pollOptions.find((entry) => entry.id === option.id), optionValues(option));

            return;
          }

          data.pollOptions.push({ id: nextId('o'), pollId: poll.id, ...optionValues(option), fixed: false });
        });
      }

      finish();
      toast(t('poll.settings.saved'));
    }

    function closePoll() {
      poll.closedAt = TODAY_ISO;
      toast(t('poll.closed.toast'));
    }

    function reopenPoll() {
      poll.closedAt = null;
      toast(t('poll.reopened.toast'));
    }

    function deletePoll() {
      const ids = original.map((option) => option.id);
      data.responses = data.responses.filter((entry) => !ids.includes(entry.optionId));
      data.pollOptions = data.pollOptions.filter((entry) => entry.pollId !== poll.id);
      data.polls = data.polls.filter((entry) => entry.id !== poll.id);
      toast(t('poll.settings.deleted'));

      if (eventId) {
        go('event', { currentEventId: eventId });

        return;
      }

      go('group', { currentGroupId: groupId });
    }

    const typeOptions = computed(() => [
      { value: 'date_finder', title: t('newPoll.type.dateFinder'), hint: t('newPoll.type.dateFinderHint') },
      { value: 'attendance', title: t('newPoll.type.attendance'), hint: t('newPoll.type.attendanceHint'), feature: 'F-07' }
    ]);
    const modeOptions = computed(() => [
      { value: 'day', title: t('newPoll.mode.day'), hint: t('newPoll.mode.dayHint') },
      { value: 'slots', title: t('newPoll.mode.slots'), hint: t('newPoll.mode.slotsHint') }
    ]);

    const pageTitle = computed(() => {
      if (editing) {
        return t('poll.edit.title');
      }

      return event.value ? t('newPoll.followUpTitle') : t('newPoll.title');
    });

    const optionsHint = computed(() => {
      if (closed.value) {
        return t('poll.settings.optionsLocked');
      }

      return editing ? t('poll.edit.optionsHint') : '';
    });

    return {
      poll, editing, allowed, form, errors, group, event, audienceCount, validOptions, typeOptions, modeOptions, pageTitle, optionsHint,
      closed, status, removing, removingOption, deleting, totalResponses, answers, locked, removable,
      applyOptions, confirmRemove, addOption, removeOption, save, closePoll, reopenPoll, deletePoll, cancel, t
    };
  },
  template: `
    <NotFoundPage v-if="!allowed" />
    <FormPage v-else :title="pageTitle" :subtitle="editing ? poll.title : t('newPoll.sub')" :back-label="editing ? t('nav.backTo', { name: poll.title }) : ''" @cancel="cancel">
      <form id="new-poll-form" @submit.prevent="save" class="form-grid" novalidate :data-feature="editing ? 'F-08' : null">
        <div class="audience" :data-feature="event ? 'F-12' : 'F-06'">
          <Icon name="users" class="icon-lg" />
          <span class="audience__text">
            <b v-if="event">{{ t('newPoll.audienceEvent', { event: event.title }) }}</b>
            <b v-else>{{ t('newPoll.audienceGroup', { group: group.name }) }}</b>
            <span>{{ event ? t('newPoll.audienceEventCount', { n: audienceCount }) : t('newPoll.audienceGroupCount', { n: audienceCount }) }}</span>
          </span>
        </div>

        <FormField :label="t('newPoll.name')" control="poll-title" required :error="errors.title">
          <input class="input" id="poll-title" v-model="form.title" :placeholder="t('newPoll.namePlaceholder')" :aria-invalid="errors.title ? 'true' : null">
        </FormField>

        <FormField :label="t('newPoll.type')" group>
          <ChoiceGroup name="poll-type" v-model="form.type" :options="typeOptions" :disabled="editing" />
        </FormField>

        <FormField :label="t('newPoll.mode')" group :hint="t('newPoll.modeLocked')">
          <ChoiceGroup name="poll-mode" v-model="form.mode" :options="modeOptions" :disabled="editing" />
        </FormField>

        <FormField v-if="editing" :label="t('poll.edit.status')" group data-feature="F-08">
          <div class="row">
            <span :class="['badge', status.cls]">{{ status.label }}</span>
            <button type="button" class="btn btn--secondary btn--sm" v-if="closed" @click="reopenPoll">{{ t('poll.actions.reopen') }}</button>
            <button type="button" class="btn btn--secondary btn--sm" v-else-if="form.type === 'attendance'" @click="closePoll">{{ t('poll.actions.close') }}</button>
            <span class="small muted" v-else>{{ t('poll.edit.closesByFixing') }}</span>
          </div>
        </FormField>

        <FormField v-if="!closed" :label="t('newPoll.options')" group required :error="errors.options">
          <OptionCalendar :mode="form.mode" :model-value="form.options" @update:model-value="applyOptions" />
        </FormField>

        <FormField :label="t('newPoll.selected', { n: validOptions.length })" group :hint="optionsHint">
          <div class="options">
            <div class="options__rows" v-if="form.options.length">
              <div :class="['option-row', form.mode === 'slots' ? 'option-row--slot' : '', editing ? 'option-row--meta' : '']" v-for="(option, index) in form.options" :key="option.id || 'new-' + index">
                <input class="input" type="date" v-model="option.date" :disabled="locked(option)" :aria-label="t('newEvent.date') + ' ' + (index + 1)">
                <template v-if="form.mode === 'slots'">
                  <input class="input" type="time" v-model="option.start" :disabled="locked(option)" :aria-label="t('newPoll.start')">
                  <input class="input" type="time" v-model="option.end" :disabled="locked(option)" :aria-label="t('newPoll.end')">
                  <input class="input input--label" type="text" v-model="option.label" :disabled="closed" :placeholder="t('newPoll.slotLabelPlaceholder')" :aria-label="t('newPoll.slotLabel')">
                </template>
                <span class="option-row__meta" v-if="editing">
                  <template v-if="option.id">{{ t('poll.settings.answers', { n: answers(option) }) }}</template>
                  <span class="badge badge--success" v-if="option.fixed">{{ t('group.polls.fixed') }}</span>
                </span>
                <button type="button" class="btn btn--ghost btn--icon btn--sm" :disabled="!removable(option)" @click="removeOption(index)" :aria-label="t('newPoll.removeOption')"><Icon name="minus" /></button>
              </div>
            </div>
            <p class="field__hint" v-else>{{ t('newPoll.noOptions') }}</p>
            <div class="alert alert--warning" v-if="removingOption" role="alert" data-feature="F-08">
              <Icon name="question" />
              <span class="alert__body">
                {{ t('poll.settings.removeConfirm', { n: answers(removingOption) }) }}
                <button type="button" class="btn btn--danger btn--sm" @click="confirmRemove">{{ t('poll.settings.remove') }}</button>
                <button type="button" class="btn btn--ghost btn--sm" @click="removing = null">{{ t('poll.settings.keep') }}</button>
              </span>
            </div>
            <button type="button" class="btn btn--soft btn--sm" v-if="!closed" @click="addOption"><Icon name="plus" /> {{ t('newPoll.addOption') }}</button>
          </div>
        </FormField>
      </form>

      <template v-if="editing">
        <hr class="divider">
        <section class="settings-block" data-feature="F-08">
          <h2>{{ t('poll.settings.delete') }}</h2>
          <template v-if="deleting">
            <p>{{ t('poll.settings.deleteConfirm', { title: poll.title, n: totalResponses }) }}</p>
            <div class="row">
              <button type="button" class="btn btn--danger btn--sm" @click="deletePoll">{{ t('poll.settings.delete') }}</button>
              <button type="button" class="btn btn--ghost btn--sm" @click="deleting = false">{{ t('dialog.cancel') }}</button>
            </div>
          </template>
          <button v-else type="button" class="btn btn--danger btn--sm" @click="deleting = true">{{ t('poll.settings.delete') }}</button>
        </section>
      </template>

      <template #actions>
        <button type="button" class="btn btn--secondary" @click="cancel">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-poll-form" class="btn btn--primary">{{ editing ? t('poll.edit.save') : t('newPoll.create') }}</button>
      </template>
    </FormPage>`
};

export const CreateEventView = {
  setup() {
    const props = ui.formContext;
    const group = computed(() => h.group(props.groupId));
    const form = reactive({ title: '', date: '', time: '', end: '', location: '', description: '' });
    const errors = reactive({ title: '', date: '' });
    const memberCount = computed(() => h.membersOf(props.groupId).length);

    function create() {
      errors.title = form.title.trim() ? '' : t('newEvent.titleRequired');
      errors.date = form.date ? '' : t('newEvent.dateRequired');

      if (errors.title || errors.date) {
        return;
      }

      const event = {
        id: nextId('e'),
        groupId: props.groupId,
        organizerId: currentUser.value.id,
        title: form.title.trim(),
        description: form.description.trim(),
        location: form.location.trim(),
        start: form.date,
        startTime: form.time || null,
        end: form.end || null,
        originOptionId: null,
        parentEventId: null
      };
      data.events.push(event);
      h.membersOf(props.groupId).forEach((accountId) => {
        data.participations.push({ eventId: event.id, accountId, status: accountId === currentUser.value.id ? 'accepted' : 'pending' });
      });
      finish();
      toast(t('newEvent.created'));
    }

    return { group, form, errors, memberCount, create, cancel, t };
  },
  template: `
    <FormPage :title="t('newEvent.title')" :subtitle="t('newEvent.sub')" @cancel="cancel">
      <form id="new-event-form" @submit.prevent="create" class="form-grid" novalidate>
        <FormField :label="t('newEvent.name')" control="event-title" required :error="errors.title">
          <input class="input" id="event-title" v-model="form.title" :placeholder="t('newEvent.namePlaceholder')" :aria-invalid="errors.title ? 'true' : null">
        </FormField>
        <div class="form-grid form-grid--2">
          <FormField :label="t('newEvent.date')" control="event-date" required :error="errors.date">
            <input class="input" id="event-date" type="date" v-model="form.date" :aria-invalid="errors.date ? 'true' : null">
          </FormField>
          <FormField :label="t('newEvent.time')" control="event-time" :hint="t('newEvent.timeHint')">
            <input class="input" id="event-time" type="time" v-model="form.time">
          </FormField>
        </div>
        <FormField :label="t('newEvent.location')" control="event-location">
          <input class="input" id="event-location" v-model="form.location" :placeholder="t('newEvent.locationPlaceholder')">
        </FormField>
        <FormField :label="t('newEvent.description')" control="event-description">
          <textarea class="textarea" id="event-description" v-model="form.description" :placeholder="t('newEvent.descriptionPlaceholder')"></textarea>
        </FormField>
        <div class="audience" data-feature="F-11">
          <Icon name="users" class="icon-lg" />
          <span class="audience__text"><b>{{ t('newEvent.audience', { n: memberCount - 1 }) }}</b></span>
        </div>
      </form>
      <template #actions>
        <button type="button" class="btn btn--secondary" @click="cancel">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-event-form" class="btn btn--primary">{{ t('newEvent.create') }}</button>
      </template>
    </FormPage>`
};

export const CreateListView = {
  components: { NotFoundPage },
  setup() {
    const props = ui.formContext;
    const user = currentUser;
    // edit-mode
    const list = props.editListId ? h.list(props.editListId) : null;
    const editing = !!list;
    const groupId = editing ? listGroupId(list) : props.groupId;
    const eventId = editing ? list.eventId : props.eventId;
    const group = computed(() => (groupId ? h.group(groupId) : null));
    const event = computed(() => (eventId ? h.event(eventId) : null));
    const isOwner = computed(() => editing && list.ownerId === user.value.id);
    const allowed = computed(() => {
      if (props.editListId && !list) {
        return false;
      }

      if (!editing) {
        return true;
      }

      return isOwner.value || (!!groupId && h.canManage(groupId, user.value.id));
    });

    const form = reactive({
      title: editing ? list.title : '',
      description: editing ? list.description || '' : '',
      date: editing ? list.date || '' : '',
      membersCanAdd: editing ? list.membersCanAddItems : true,
      attendanceOptionId: editing ? list.attendanceOptionId || '' : ''
    });
    const where = ref(eventId ? 'event' : groupId ? 'group' : 'standalone');
    const error = ref('');

    function save() {
      if (!form.title.trim()) {
        error.value = t('newList.titleRequired');

        return;
      }

      const values = {
        title: form.title.trim(),
        description: form.description.trim(),
        date: form.date || null,
        membersCanAddItems: form.membersCanAdd
      };

      if (editing) {
        Object.assign(list, values, { attendanceOptionId: form.attendanceOptionId || null });
        finish();
        toast(t('list.settings.saved'));

        return;
      }

      data.lists.push({
        id: nextId('l'),
        ...values,
        ownerId: user.value.id,
        groupId: where.value === 'group' ? groupId : null,
        eventId: where.value === 'event' ? eventId : null,
        shareToken: null
      });
      finish();
      toast(t('newList.created'));
    }

    const whereOptions = computed(() => [
      event.value && { value: 'event', title: t('newList.inEvent', { event: event.value.title }), hint: t('newList.inEventHint') },
      group.value && { value: 'group', title: t('newList.inGroup', { group: group.value.name }), hint: t('newList.inGroupHint') },
      { value: 'standalone', title: t('newList.standalone'), hint: t('newList.standaloneHint') }
    ].filter(Boolean));

    // edit-mode: attendance link (F-18), custom fields (F-16), share link (F-19), duplicate (F-17), delete (F-13).
    // Fields and the share link apply at once, the details above apply on save.
    const attendancePolls = computed(() => (editing && list.groupId ? attendancePollsOf(list.groupId) : []));
    const fields = computed(() => (editing ? data.customFields.filter((field) => field.listId === list.id) : []));
    const newField = ref('');
    const fieldToDelete = ref(null);
    const deleting = ref(false);
    const shareUrl = computed(() => (editing && list.shareToken ? `/shared/${list.shareToken}` : ''));

    function addField() {
      const name = newField.value.trim();

      if (!name) {
        return;
      }

      data.customFields.push({ id: nextId('cf'), listId: list.id, name });
      newField.value = '';
    }

    function valueCount(field) {
      return h.items(list.id).filter((item) => (item.fieldValues || {})[field.id]).length;
    }

    function deleteField() {
      const field = fieldToDelete.value;
      h.items(list.id).forEach((item) => {
        if (!item.fieldValues) {
          return;
        }

        delete item.fieldValues[field.id];
      });
      data.customFields.splice(data.customFields.indexOf(field), 1);
      fieldToDelete.value = null;
    }

    function newToken() {
      return Math.random().toString(36).slice(2, 8);
    }

    function enableLink() {
      list.shareToken = newToken();
      toast(t('list.share.enabled'));
    }

    function regenerateLink() {
      list.shareToken = newToken();
      toast(t('list.share.regenerated'));
    }

    function disableLink() {
      list.shareToken = null;
      toast(t('list.share.disabled'));
    }

    async function copyLink() {
      try {
        await navigator.clipboard.writeText(`${location.origin}${shareUrl.value}`);
      } catch {
        /* clipboard access is a convenience only */
      }

      toast(t('list.share.copied'));
    }

    function previewAsGuest() {
      ui.states.list = 'guest';
      go('list', { currentListId: list.id });
    }

    // Duplicate (F-17): title, description, items and custom fields; no claims, comments or date.
    function duplicate() {
      const copy = { ...list, id: nextId('l'), ownerId: user.value.id, date: null, shareToken: null };
      const fieldIds = {};
      data.lists.push(copy);
      fields.value.forEach((field) => {
        fieldIds[field.id] = nextId('cf');
        data.customFields.push({ id: fieldIds[field.id], listId: copy.id, name: field.name });
      });
      h.items(list.id).forEach((item) => {
        const values = Object.fromEntries(Object.entries(item.fieldValues || {}).map(([id, value]) => [fieldIds[id], value]));
        data.items.push({ ...item, id: nextId('i'), listId: copy.id, fieldValues: values });
      });
      toast(t('list.settings.duplicated'));
      go('list', { currentListId: copy.id });
    }

    function deleteList() {
      const itemIds = h.items(list.id).map((item) => item.id);
      data.claims = data.claims.filter((claim) => !itemIds.includes(claim.itemId));
      data.comments = data.comments.filter((comment) => comment.listId !== list.id);
      data.customFields = data.customFields.filter((field) => field.listId !== list.id);
      data.items = data.items.filter((item) => item.listId !== list.id);
      data.lists.splice(data.lists.indexOf(list), 1);
      toast(t('list.settings.deleted'));

      if (eventId) {
        go('event', { currentEventId: eventId });

        return;
      }

      if (groupId) {
        go('group', { currentGroupId: groupId });

        return;
      }

      go('dashboard');
    }

    return {
      list, editing, allowed, isOwner, form, where, error, group, event, whereOptions, save, cancel,
      attendancePolls, fields, newField, fieldToDelete, deleting, shareUrl,
      addField, valueCount, deleteField, enableLink, regenerateLink, disableLink, copyLink, previewAsGuest, duplicate, deleteList, optionLabel, t
    };
  },
  template: `
    <NotFoundPage v-if="!allowed" />
    <FormPage v-else :title="editing ? t('list.edit.title') : t('newList.title')" :subtitle="editing ? list.title : t('newList.sub')" :back-label="editing ? t('nav.backTo', { name: list.title }) : ''" @cancel="cancel">
      <form id="new-list-form" @submit.prevent="save" class="form-grid" novalidate data-feature="F-13">
        <FormField :label="t('newList.name')" control="list-title" required :error="error">
          <input class="input" id="list-title" v-model="form.title" :placeholder="t('newList.namePlaceholder')" :aria-invalid="error ? 'true' : null">
        </FormField>
        <FormField :label="t('newList.description')" control="list-description">
          <textarea class="textarea" id="list-description" v-model="form.description" :placeholder="t('newList.descriptionPlaceholder')"></textarea>
        </FormField>
        <FormField :label="t('newList.date')" control="list-date" :hint="t('newList.dateHint')">
          <input class="input input--date" id="list-date" type="date" v-model="form.date">
        </FormField>
        <FormField :label="t('newList.where')" group v-if="!editing && (group || event)">
          <ChoiceGroup name="list-where" v-model="where" :options="whereOptions" />
        </FormField>
        <label class="check" data-feature="F-14">
          <input type="checkbox" v-model="form.membersCanAdd">
          <span class="check__text">
            <span>{{ t('newList.membersCanAdd') }}</span>
            <span class="check__hint">{{ t('newList.membersCanAddHint') }}</span>
          </span>
        </label>
        <FormField v-if="attendancePolls.length" :label="t('list.attendance.listLabel')" control="list-attendance" :hint="t('list.attendance.listHint')" data-feature="F-18">
          <select class="select" id="list-attendance" v-model="form.attendanceOptionId">
            <option value="">{{ t('list.attendance.none') }}</option>
            <optgroup v-for="entry in attendancePolls" :key="entry.poll.id" :label="entry.poll.title">
              <option v-for="option in entry.options" :key="option.id" :value="option.id">{{ optionLabel(option) }}</option>
            </optgroup>
          </select>
        </FormField>
      </form>

      <template v-if="isOwner">
        <hr class="divider">
        <section class="settings-block" data-feature="F-16">
          <h2>{{ t('list.fields.title') }}</h2>
          <p>{{ t('list.fields.hint') }}</p>
          <ul class="field-rows" v-if="fields.length">
            <li class="field-row" v-for="field in fields" :key="field.id">
              <input class="input input--sm" v-model="field.name" :aria-label="t('list.fields.name')">
              <button type="button" class="btn btn--ghost btn--icon btn--sm" :aria-label="t('list.fields.remove')" @click="fieldToDelete = field"><Icon name="x" /></button>
            </li>
          </ul>
          <div class="alert alert--warning" v-if="fieldToDelete" role="alert">
            <Icon name="question" />
            <span class="alert__body">
              {{ t('list.fields.confirm', { name: fieldToDelete.name, n: valueCount(fieldToDelete) }) }}
              <button type="button" class="btn btn--danger btn--sm" @click="deleteField">{{ t('list.fields.confirmButton') }}</button>
              <button type="button" class="btn btn--ghost btn--sm" @click="fieldToDelete = null">{{ t('dialog.cancel') }}</button>
            </span>
          </div>
          <div class="field-add">
            <input class="input input--sm" v-model="newField" :placeholder="t('list.fields.placeholder')" :aria-label="t('list.fields.add')" @keydown.enter.prevent="addField">
            <button type="button" class="btn btn--secondary btn--sm" @click="addField"><Icon name="plus" /> {{ t('list.fields.add') }}</button>
          </div>
        </section>

        <hr class="divider">
        <section class="settings-block" data-feature="F-19">
          <h2>{{ t('list.share.title') }}</h2>
          <p>{{ t('list.share.hint') }}</p>
          <template v-if="list.shareToken">
            <div class="share-link">
              <code class="share-link__url">{{ shareUrl }}</code>
              <button type="button" class="btn btn--secondary btn--sm" @click="copyLink"><Icon name="link" /> {{ t('list.share.copy') }}</button>
            </div>
            <div class="row">
              <button type="button" class="btn btn--ghost btn--sm" @click="regenerateLink">{{ t('list.share.regenerate') }}</button>
              <button type="button" class="btn btn--ghost btn--sm" @click="disableLink">{{ t('list.share.disable') }}</button>
              <button type="button" class="btn btn--ghost btn--sm" @click="previewAsGuest">{{ t('list.share.preview') }}</button>
            </div>
          </template>
          <button type="button" class="btn btn--secondary btn--sm" v-else @click="enableLink"><Icon name="link" /> {{ t('list.share.enable') }}</button>
        </section>

        <hr class="divider">
        <section class="settings-block" data-feature="F-17">
          <h2>{{ t('list.settings.duplicate') }}</h2>
          <p>{{ t('list.settings.duplicateHint') }}</p>
          <button type="button" class="btn btn--secondary btn--sm" @click="duplicate">{{ t('list.settings.duplicate') }}</button>
        </section>
      </template>

      <template v-if="editing">
        <hr class="divider">
        <section class="settings-block" data-feature="F-13">
          <h2>{{ t('list.settings.delete') }}</h2>
          <template v-if="deleting">
            <p>{{ t('list.settings.deleteText', { title: list.title }) }}</p>
            <div class="row">
              <button type="button" class="btn btn--danger btn--sm" @click="deleteList">{{ t('list.settings.delete') }}</button>
              <button type="button" class="btn btn--ghost btn--sm" @click="deleting = false">{{ t('dialog.cancel') }}</button>
            </div>
          </template>
          <template v-else>
            <p>{{ t('list.settings.deleteHint') }}</p>
            <button type="button" class="btn btn--danger btn--sm" @click="deleting = true">{{ t('list.settings.delete') }}</button>
          </template>
        </section>
      </template>

      <template #actions>
        <button type="button" class="btn btn--secondary" @click="cancel">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-list-form" class="btn btn--primary">{{ editing ? t('list.edit.save') : t('newList.create') }}</button>
      </template>
    </FormPage>`
};

// Create pages for poll, event and list (requirements v1.3, section 6). They read their
// context (group, optional event) from ui.formContext and return to ui.returnView on cancel.

import { ref, reactive, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, data, currentUser, toast, nextId, go } from '../core.js';
import { t } from '../i18n.js';

function cancel() {
  go(ui.returnView || 'dashboard');
}

function finish() {
  go(ui.returnView || 'dashboard');
}

export const CreatePollView = {
  setup() {
    const props = ui.formContext;
    const group = computed(() => h.group(props.groupId));
    const event = computed(() => (props.eventId ? h.event(props.eventId) : null));
    const audienceCount = computed(() => (event.value ? h.participants(event.value.id, 'accepted').length : h.membersOf(props.groupId).length));

    const form = reactive({ title: '', type: 'date_finder', mode: 'day', options: [] });
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

    function addOption() {
      const last = form.options[form.options.length - 1];
      form.options.push(form.mode === 'slots'
        ? { date: '', start: last ? last.start : '19:30', end: last ? last.end : '22:00', label: '' }
        : { date: '', start: null, end: null, label: '' });
    }

    function removeOption(index) {
      form.options.splice(index, 1);
    }

    function create() {
      errors.title = form.title.trim() ? '' : t('newPoll.titleRequired');
      errors.options = validOptions.value.length ? '' : t('newPoll.optionsRequired');

      if (errors.title || errors.options) {
        return;
      }

      const poll = {
        id: nextId('p'),
        groupId: props.groupId,
        organizerId: currentUser.value.id,
        title: form.title.trim(),
        type: form.type,
        mode: form.mode,
        closedAt: null,
        eventId: props.eventId || null,
        createdAt: '2026-10-25'
      };
      data.polls.push(poll);
      validOptions.value.forEach((option) => {
        data.pollOptions.push({
          id: nextId('o'),
          pollId: poll.id,
          date: option.date,
          start: form.mode === 'slots' ? option.start : null,
          end: form.mode === 'slots' ? option.end : null,
          label: form.mode === 'slots' ? option.label : '',
          fixed: false
        });
      });
      finish();
      toast(t('newPoll.created'));
    }

    const typeOptions = computed(() => [
      { value: 'date_finder', title: t('newPoll.type.dateFinder'), hint: t('newPoll.type.dateFinderHint') },
      { value: 'attendance', title: t('newPoll.type.attendance'), hint: t('newPoll.type.attendanceHint'), feature: 'F-07' }
    ]);
    const modeOptions = computed(() => [
      { value: 'day', title: t('newPoll.mode.day'), hint: t('newPoll.mode.dayHint') },
      { value: 'slots', title: t('newPoll.mode.slots'), hint: t('newPoll.mode.slotsHint') }
    ]);

    return { form, errors, group, event, audienceCount, validOptions, typeOptions, modeOptions, addOption, removeOption, create, cancel, t };
  },
  template: `
    <FormPage :title="event ? t('newPoll.followUpTitle') : t('newPoll.title')" :subtitle="t('newPoll.sub')" @cancel="cancel">
      <form id="new-poll-form" @submit.prevent="create" class="form-grid" novalidate>
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
          <ChoiceGroup name="poll-type" v-model="form.type" :options="typeOptions" />
        </FormField>

        <FormField :label="t('newPoll.mode')" group :hint="t('newPoll.modeLocked')">
          <ChoiceGroup name="poll-mode" v-model="form.mode" :options="modeOptions" />
        </FormField>

        <FormField :label="t('newPoll.options')" group required :error="errors.options">
          <OptionCalendar :mode="form.mode" v-model="form.options" />
        </FormField>

        <FormField :label="t('newPoll.selected', { n: validOptions.length })" group>
          <div class="options">
            <div class="options__rows" v-if="form.options.length">
              <div :class="['option-row', form.mode === 'slots' ? 'option-row--slot' : '']" v-for="(option, index) in form.options" :key="index">
                <input class="input" type="date" v-model="option.date" :aria-label="t('newEvent.date') + ' ' + (index + 1)">
                <template v-if="form.mode === 'slots'">
                  <input class="input" type="time" v-model="option.start" :aria-label="t('newPoll.start')">
                  <input class="input" type="time" v-model="option.end" :aria-label="t('newPoll.end')">
                  <input class="input input--label" type="text" v-model="option.label" :placeholder="t('newPoll.slotLabelPlaceholder')" :aria-label="t('newPoll.slotLabel')">
                </template>
                <button type="button" class="btn btn--ghost btn--icon btn--sm" @click="removeOption(index)" :aria-label="t('newPoll.removeOption')"><Icon name="minus" /></button>
              </div>
            </div>
            <p class="field__hint" v-else>{{ t('newPoll.noOptions') }}</p>
            <button type="button" class="btn btn--soft btn--sm" @click="addOption"><Icon name="plus" /> {{ t('newPoll.addOption') }}</button>
          </div>
        </FormField>
      </form>
      <template #actions>
        <button type="button" class="btn btn--secondary" @click="cancel">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-poll-form" class="btn btn--primary">{{ t('newPoll.create') }}</button>
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
  setup() {
    const props = ui.formContext;
    const title = ref('');
    const description = ref('');
    const date = ref('');
    const where = ref(props.eventId ? 'event' : props.groupId ? 'group' : 'standalone');
    const membersCanAdd = ref(true);
    const error = ref('');
    const group = computed(() => (props.groupId ? h.group(props.groupId) : null));
    const event = computed(() => (props.eventId ? h.event(props.eventId) : null));

    function create() {
      if (!title.value.trim()) {
        error.value = t('newList.titleRequired');

        return;
      }

      const list = {
        id: nextId('l'),
        title: title.value.trim(),
        description: description.value.trim(),
        ownerId: currentUser.value.id,
        groupId: where.value === 'group' ? props.groupId : null,
        eventId: where.value === 'event' ? props.eventId : null,
        date: date.value || null,
        membersCanAddItems: membersCanAdd.value,
        shareToken: null
      };
      data.lists.push(list);
      finish();
      toast(t('newList.created'));
    }

    const whereOptions = computed(() => [
      event.value && { value: 'event', title: t('newList.inEvent', { event: event.value.title }), hint: t('newList.inEventHint') },
      group.value && { value: 'group', title: t('newList.inGroup', { group: group.value.name }), hint: t('newList.inGroupHint') },
      { value: 'standalone', title: t('newList.standalone'), hint: t('newList.standaloneHint') }
    ].filter(Boolean));

    return { title, description, date, where, membersCanAdd, error, group, event, whereOptions, create, cancel, t };
  },
  template: `
    <FormPage :title="t('newList.title')" :subtitle="t('newList.sub')" @cancel="cancel">
      <form id="new-list-form" @submit.prevent="create" class="form-grid" novalidate>
        <FormField :label="t('newList.name')" control="list-title" required :error="error">
          <input class="input" id="list-title" v-model="title" :placeholder="t('newList.namePlaceholder')" :aria-invalid="error ? 'true' : null">
        </FormField>
        <FormField :label="t('newList.description')" control="list-description">
          <textarea class="textarea" id="list-description" v-model="description" :placeholder="t('newList.descriptionPlaceholder')"></textarea>
        </FormField>
        <FormField :label="t('newList.date')" control="list-date" :hint="t('newList.dateHint')">
          <input class="input input--date" id="list-date" type="date" v-model="date">
        </FormField>
        <FormField :label="t('newList.where')" group v-if="group || event">
          <ChoiceGroup name="list-where" v-model="where" :options="whereOptions" />
        </FormField>
        <label class="check" data-feature="F-14">
          <input type="checkbox" v-model="membersCanAdd">
          <span class="check__text">
            <span>{{ t('newList.membersCanAdd') }}</span>
            <span class="check__hint">{{ t('newList.membersCanAddHint') }}</span>
          </span>
        </label>
      </form>
      <template #actions>
        <button type="button" class="btn btn--secondary" @click="cancel">{{ t('dialog.cancel') }}</button>
        <button type="submit" form="new-list-form" class="btn btn--primary">{{ t('newList.create') }}</button>
      </template>
    </FormPage>`
};

// Shared UI components, registered by name in main.js.

import { ref, onMounted, onBeforeUnmount, nextTick } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { h, ui } from './core.js';
import { t, dayNumber, monthShort } from './i18n.js';

const paths = {
  check: '<path d="M5 12.5l4.2 4.2L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
  x: '<path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
  question: '<path d="M9.5 9.5a2.5 2.5 0 015 0c0 1.8-2.5 2-2.5 4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17.5" r="1.3" fill="currentColor"/>',
  plus: '<path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
  minus: '<path d="M6 12h12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
  back: '<path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
  chevron: '<path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
  bell: '<path d="M6 16V11a6 6 0 0112 0v5l1.5 2h-15z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M10 20a2 2 0 004 0" fill="none" stroke="currentColor" stroke-width="2"/>',
  calendar: '<rect x="3.5" y="5" width="17" height="15.5" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3.5 10h17M8 3v4M16 3v4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  poll: '<path d="M5 19V11M12 19V5M19 19v-7" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
  grid: '<rect x="4" y="4" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="13" y="4" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="4" y="13" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/><rect x="13" y="13" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="2"/>',
  list: '<path d="M8 7h12M8 12h12M8 17h12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="4.5" cy="7" r="1.3" fill="currentColor"/><circle cx="4.5" cy="12" r="1.3" fill="currentColor"/><circle cx="4.5" cy="17" r="1.3" fill="currentColor"/>',
  users: '<circle cx="9" cy="8.5" r="3.2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3.5 19c.6-3 2.8-4.5 5.5-4.5s4.9 1.5 5.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M15.5 12.2a3 3 0 100-5.6M17 14.6c2 .4 3.3 1.8 3.7 4.4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  pin: '<path d="M12 21s6-5.6 6-11a6 6 0 10-12 0c0 5.4 6 11 6 11z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" stroke-width="2"/>',
  clock: '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  link: '<path d="M10 14a4 4 0 010-5.7l2.3-2.3a4 4 0 115.7 5.7l-1.2 1.2M14 10a4 4 0 010 5.7l-2.3 2.3a4 4 0 11-5.7-5.7l1.2-1.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  mail: '<rect x="3.5" y="5.5" width="17" height="13" rx="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 8l8 5.5L20 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
  settings: '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3.5v2.3M12 18.2v2.3M3.5 12h2.3M18.2 12h2.3M6 6l1.6 1.6M16.4 16.4L18 18M6 18l1.6-1.6M16.4 7.6L18 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  logout: '<path d="M10 5H6.5A2 2 0 004.5 7v10a2 2 0 002 2H10M15 16l4-4-4-4M19 12H9.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
  heart: '<path d="M12 20s-7-4.4-7-9.6A4 4 0 0112 8a4 4 0 017 2.4C19 15.6 12 20 12 20z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
  sparkle: '<path d="M12 4l1.8 5.2L19 11l-5.2 1.8L12 18l-1.8-5.2L5 11l5.2-1.8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
  globe: '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3.5 12h17M12 3.5c3 3 3 14 0 17M12 3.5c-3 3-3 14 0 17" fill="none" stroke="currentColor" stroke-width="2"/>',
  dots: '<circle cx="6" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="18" cy="12" r="1.6" fill="currentColor"/>',
  comment: '<path d="M5 5.5h14a1.5 1.5 0 011.5 1.5v8a1.5 1.5 0 01-1.5 1.5h-7L8 20v-3.5H5A1.5 1.5 0 013.5 15V7A1.5 1.5 0 015 5.5z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>',
  // invite-link
  lock: '<rect x="5" y="11" width="14" height="10" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 018 0v3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  unlock: '<rect x="5" y="11" width="14" height="10" rx="2.5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 11V8a4 4 0 017.6-1.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
  share: '<path d="M4 13v6a2 2 0 002 2h12a2 2 0 002-2v-6M16 7l-4-4-4 4M12 3v12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
};

export const Icon = {
  props: { name: { type: String, required: true } },
  computed: {
    body() {
      return paths[this.name] || '';
    }
  },
  template: `<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" v-html="body"></svg>`
};

export const Avatar = {
  props: { id: String, size: { type: String, default: '' } },
  computed: {
    account() { return h.account(this.id); },
    initials() { return h.initials(this.id); },
    classes() {
      const account = this.account || {};

      return [
        'avatar',
        this.size ? `avatar--${this.size}` : '',
        `avatar--tone-${account.tone || 5}`,
        !account.name ? 'avatar--pending' : ''
      ];
    },
    label() {
      return this.account ? (this.account.name || this.account.email) : '';
    }
  },
  template: `<span :class="classes" :title="label" role="img" :aria-label="label">{{ initials }}</span>`
};

export const Mark = {
  props: { answer: { type: String, default: 'none' } },
  computed: {
    icon() {
      return { yes: 'check', maybe: 'question', no: 'x' }[this.answer] || '';
    }
  },
  template: `<span :class="['mark', 'mark--' + answer]"><Icon v-if="icon" :name="icon" /></span>`
};

export const DateBlock = {
  props: { date: { type: String, required: true }, fixed: Boolean },
  computed: {
    day() { return dayNumber(this.date); },
    month() { return monthShort(this.date); }
  },
  template: `
    <div :class="['date-block', fixed ? 'date-block--fixed' : '']" aria-hidden="true">
      <span class="date-block__day">{{ day }}</span>
      <span class="date-block__month">{{ month }}</span>
    </div>`
};

export const EmptyState = {
  props: { title: String, text: String, icon: String },
  template: `
    <div class="empty">
      <Icon v-if="icon" :name="icon" class="empty__icon" />
      <div class="empty__title">{{ title }}</div>
      <p class="empty__text" v-if="text">{{ text }}</p>
      <div class="empty__actions" v-if="$slots.default"><slot /></div>
    </div>`
};

export const AvatarStack = {
  props: { ids: { type: Array, default: () => [] }, max: { type: Number, default: 4 } },
  computed: {
    shown() { return this.ids.slice(0, this.max); },
    rest() { return Math.max(0, this.ids.length - this.max); },
    moreLabel() { return t('common.more', { n: this.rest }); }
  },
  template: `
    <span class="avatar-stack">
      <Avatar v-for="id in shown" :key="id" :id="id" size="sm" />
      <span class="avatar-stack__more" v-if="rest">{{ moreLabel }}</span>
    </span>`
};

export const AppDialog = {
  props: { title: String, subtitle: String, wide: Boolean },
  emits: ['close'],
  setup(props, { emit }) {
    const panel = ref(null);
    const onKey = (event) => {
      if (event.key === 'Escape') {
        emit('close');
      }
    };

    onMounted(() => {
      document.addEventListener('keydown', onKey);
      document.body.style.overflow = 'hidden';
      nextTick(() => {
        const first = panel.value && panel.value.querySelector('input, select, textarea, button');

        if (first) {
          first.focus();
        }
      });
    });

    onBeforeUnmount(() => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    });

    return { panel, closeLabel: () => t('dialog.close') };
  },
  template: `
    <div class="dialog-backdrop" @mousedown.self="$emit('close')">
      <div :class="['dialog', wide ? 'dialog--wide' : '']" role="dialog" aria-modal="true" :aria-label="title" ref="panel">
        <div class="dialog__head">
          <div>
            <h2>{{ title }}</h2>
            <p class="dialog__sub" v-if="subtitle">{{ subtitle }}</p>
          </div>
          <button type="button" class="btn btn--ghost btn--icon btn--sm" :aria-label="closeLabel()" @click="$emit('close')"><Icon name="x" /></button>
        </div>
        <div class="dialog__body"><slot /></div>
        <div class="dialog__foot" v-if="$slots.foot"><slot name="foot" /></div>
      </div>
    </div>`
};

export const ProgressBar = {
  props: { done: { type: Number, required: true }, total: { type: Number, required: true } },
  computed: {
    width() {
      return `${Math.round(100 * this.done / Math.max(1, this.total))}%`;
    }
  },
  template: `<span class="progress" role="img" :aria-label="done + ' / ' + total"><span class="progress__bar" :style="{ width }"></span></span>`
};

// One spacing contract for every form control: label, control, then error or hint.
export const FormField = {
  props: {
    label: { type: String, required: true },
    control: String,
    hint: String,
    error: String,
    required: Boolean,
    group: Boolean
  },
  computed: {
    labelId() {
      return `${this.control || this.label.replace(/\W+/g, '-').toLowerCase()}-label`;
    }
  },
  template: `
    <div class="field" :role="group ? 'group' : null" :aria-labelledby="group ? labelId : null">
      <label v-if="!group" class="field__label" :for="control">{{ label }} <span class="req" v-if="required" aria-hidden="true">*</span></label>
      <span v-else class="field__label" :id="labelId">{{ label }} <span class="req" v-if="required" aria-hidden="true">*</span></span>
      <slot />
      <span class="field__error" v-if="error" role="alert">{{ error }}</span>
      <span class="field__hint" v-else-if="hint">{{ hint }}</span>
    </div>`
};

// Radio choices rendered as cards. options: [{ value, title, hint, feature }]
export const ChoiceGroup = {
  props: {
    modelValue: { type: String, required: true },
    name: { type: String, required: true },
    options: { type: Array, required: true }
  },
  emits: ['update:modelValue'],
  template: `
    <div class="choice-group">
      <label class="choice" v-for="option in options" :key="option.value" :data-feature="option.feature || null">
        <input type="radio" :name="name" :value="option.value" :checked="option.value === modelValue" @change="$emit('update:modelValue', option.value)">
        <span class="choice__title">{{ option.title }}</span>
        <span class="choice__hint" v-if="option.hint">{{ option.hint }}</span>
      </label>
    </div>`
};

// Page frame for the create and edit pages: back link, title, one form column, sticky action bar.
export const FormPage = {
  props: { title: { type: String, required: true }, subtitle: String, backLabel: String }, // edit-mode: backLabel
  emits: ['cancel'],
  setup() {
    return { t };
  },
  template: `
    <main class="container page form-page">
      <div class="form-page__head">
        <button type="button" class="back" @click="$emit('cancel')"><Icon name="back" /> {{ backLabel || t('nav.back') }}</button>
        <h1>{{ title }}</h1>
        <p class="lede" v-if="subtitle">{{ subtitle }}</p>
      </div>
      <div class="form-page__body"><slot /></div>
      <div class="form-actions" v-if="$slots.actions"><slot name="actions" /></div>
    </main>`
};

export const LangSwitch = {
  setup() {
    return { ui, label: () => t('welcome.language') };
  },
  template: `
    <div class="lang-switch" role="group" :aria-label="label()" data-feature="F-24">
      <button type="button" :aria-pressed="ui.lang === 'en'" @click="ui.lang = 'en'" lang="en">English</button>
      <button type="button" :aria-pressed="ui.lang === 'de'" @click="ui.lang = 'de'" lang="de">Deutsch</button>
    </div>`
};

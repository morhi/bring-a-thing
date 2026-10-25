// App shell: header with wordmark, notifications and user menu; review bar; toasts.

import { computed, ref, onMounted, onBeforeUnmount } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, h, currentUser, go, views, viewState, features } from './core.js';
import { t } from './i18n.js';

export const Wordmark = {
  props: { large: Boolean, asButton: { type: Boolean, default: true } },
  setup() {
    const home = () => go(ui.view === 'welcome' ? 'welcome' : 'dashboard');

    return { home };
  },
  template: `
    <button v-if="asButton" type="button" :class="['wordmark', large ? 'wordmark--lg' : '']" @click="home" aria-label="Bring a Thing">
      <span>Bring a</span><span class="tag" aria-hidden="true">Thing</span>
    </button>
    <span v-else :class="['wordmark', large ? 'wordmark--lg' : '']" aria-label="Bring a Thing">
      <span>Bring a</span><span class="tag" aria-hidden="true">Thing</span>
    </span>`
};

export const AppHeader = {
  setup() {
    const menu = ref(null);
    const user = currentUser;
    const unread = computed(() => h.unread(user.value.id));
    const onDocClick = (event) => {
      if (menu.value && !menu.value.contains(event.target)) {
        ui.menuOpen = false;
      }
    };

    onMounted(() => document.addEventListener('click', onDocClick));
    onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

    return { ui, user, unread, menu, go, t };
  },
  template: `
    <header class="app-header">
      <div class="container app-header__inner">
        <Wordmark />
        <span class="app-header__spacer"></span>
        <div class="header-actions">
          <button type="button" class="btn btn--ghost btn--icon bell" :aria-label="t('common.notifications.unread', { n: unread })" data-feature="F-23">
            <Icon name="bell" />
            <span class="bell__count" v-if="unread" aria-hidden="true">{{ unread }}</span>
          </button>
          <div class="user-menu" ref="menu">
            <button type="button" class="user-menu__button" :aria-expanded="ui.menuOpen" aria-controls="user-menu" :aria-label="t('nav.menu')" @click="ui.menuOpen = !ui.menuOpen">
              <Avatar :id="user.id" />
            </button>
            <div class="menu" id="user-menu" v-if="ui.menuOpen" role="menu">
              <div class="menu__head">
                <span class="menu__name">{{ user.name }}</span>
                <span class="menu__email">{{ user.email }}</span>
              </div>
              <hr class="divider">
              <button type="button" class="menu__item" role="menuitem" @click="go('settings')">{{ t('nav.settings') }} <Icon name="settings" class="icon-md icon-soft" /></button>
              <button type="button" class="menu__item" role="menuitem" @click="go('friends')" data-feature="F-05">{{ t('nav.friends') }} <Icon name="heart" class="icon-md icon-soft" /></button>
              <hr class="divider">
              <button type="button" class="menu__item" role="menuitem" @click="go('welcome')">{{ t('nav.logout') }} <Icon name="logout" class="icon-md icon-soft" /></button>
            </div>
          </div>
        </div>
      </div>
    </header>`
};

export const Toasts = {
  setup() {
    return { ui };
  },
  template: `
    <div class="toasts" aria-live="polite">
      <div class="toast" v-for="item in ui.toasts" :key="item.id"><Icon name="check" /> {{ item.message }}</div>
    </div>`
};

export const ReviewBar = {
  setup() {
    const state = computed({
      get: () => viewState(ui.view).value,
      set: (value) => { viewState(ui.view).value = value; }
    });
    const states = computed(() => (views[ui.view] || {}).states || []);
    const viewFeatures = computed(() => {
      const nodes = document.querySelectorAll('main [data-feature]');
      const ids = new Set(Array.from(nodes).map((node) => node.dataset.feature));

      return Array.from(ids).sort();
    });

    return { ui, state, states, features, viewFeatures, t };
  },
  template: `
    <div class="review-bar">
      <div class="review-panel" v-if="ui.reviewOpen" id="review-panel">
        <h2>{{ t('review.title') }}</h2>
        <div class="field" v-if="ui.view !== 'welcome'">
          <span class="field__label">{{ t('review.role') }}</span>
          <div class="segmented" role="group">
            <button type="button" :aria-pressed="ui.role === 'owner'" @click="ui.role = 'owner'">Owner</button>
            <button type="button" :aria-pressed="ui.role === 'admin'" @click="ui.role = 'admin'">Admin</button>
            <button type="button" :aria-pressed="ui.role === 'member'" @click="ui.role = 'member'">{{ t('group.member.member') }}</button>
          </div>
        </div>
        <div class="field" v-if="states.length > 1">
          <label class="field__label" for="review-state">{{ t('review.state') }}</label>
          <select class="select input--sm" id="review-state" v-model="state">
            <option v-for="s in states" :key="s" :value="s">{{ t('review.state.' + s) }}</option>
          </select>
        </div>
        <div class="field">
          <span class="field__label">{{ t('review.theme') }}</span>
          <div class="segmented" role="group">
            <button type="button" :aria-pressed="ui.theme === 'system'" @click="ui.theme = 'system'">{{ t('review.theme.system') }}</button>
            <button type="button" :aria-pressed="ui.theme === 'light'" @click="ui.theme = 'light'">{{ t('review.theme.light') }}</button>
            <button type="button" :aria-pressed="ui.theme === 'dark'" @click="ui.theme = 'dark'">{{ t('review.theme.dark') }}</button>
          </div>
        </div>
        <div class="field">
          <span class="field__label">{{ t('review.language') }}</span>
          <div class="segmented" role="group">
            <button type="button" :aria-pressed="ui.lang === 'en'" @click="ui.lang = 'en'">English</button>
            <button type="button" :aria-pressed="ui.lang === 'de'" @click="ui.lang = 'de'">Deutsch</button>
          </div>
        </div>
        <label class="check">
          <input type="checkbox" v-model="ui.showFeatures">
          <span class="check__text">{{ t('review.features') }}</span>
        </label>
        <ul class="review-panel__features" v-if="ui.showFeatures">
          <li v-for="id in viewFeatures" :key="id"><b>{{ id }}</b> {{ features[id] }}</li>
        </ul>
      </div>
      <button type="button" class="review-bar__toggle" :aria-expanded="ui.reviewOpen" aria-controls="review-panel" @click="ui.reviewOpen = !ui.reviewOpen">{{ t('review.open') }}</button>
    </div>`
};

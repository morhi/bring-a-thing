// Stub view for standard pages that have no own view yet.

import { computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, go } from './core.js';
import { t } from './i18n.js';

export const StubView = {
  setup() {
    const kind = computed(() => ui.view);
    const isStandard = computed(() => ['settings', 'friends'].includes(ui.view));

    return { ui, kind, isStandard, go, t };
  },
  template: `
    <main class="container page">
      <div class="stub">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <span class="badge">{{ isStandard ? t('stub.standard') : t('stub.next.title') }}</span>
        <h1>{{ isStandard ? t('stub.' + kind + '.title') : kind.charAt(0).toUpperCase() + kind.slice(1) }}</h1>
        <p class="lede">{{ isStandard ? t('stub.' + kind + '.text') : t('stub.next.text') }}</p>
        <LangSwitch v-if="kind === 'settings'" />
      </div>
    </main>`
};

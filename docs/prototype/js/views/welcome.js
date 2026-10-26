// Start page: public, with the single login and registration form (F-01, F-24).

import { ref, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, go, viewState } from '../core.js';
import { t } from '../i18n.js';

export const WelcomeView = {
  setup() {
    const state = viewState('welcome');
    const email = ref('');
    const name = ref('');
    const password = ref('');
    const needsName = ref(false);
    const usePassword = ref(false);
    const error = ref('');

    // Known demo accounts: maurice has a password, lena has none, anyone else is new.
    const knownWithPassword = ['maurice@nordlicht.band'];
    const knownWithoutPassword = ['lena.frei@gmail.com', 'tobi.keller@bluewin.ch'];

    watch(state, (value) => {
      error.value = '';
      needsName.value = false;
      usePassword.value = false;

      if (value === 'sent') {
        email.value = email.value || 'maurice@nordlicht.band';
      }

      if (value === 'password') {
        email.value = 'maurice@nordlicht.band';
        usePassword.value = true;
      }

      if (value === 'error') {
        email.value = 'maurice@nordlicht';
        error.value = t('welcome.login.invalid');
      }

      if (value === 'expired') {
        email.value = '';
      }
    }, { immediate: true });

    const valid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value));
    const offeredPassword = ref(false);

    function submit() {
      if (!valid.value) {
        error.value = t('welcome.login.invalid');
        state.value = 'default';

        return;
      }

      error.value = '';

      if (usePassword.value) {
        go('dashboard');

        return;
      }

      if (knownWithPassword.includes(email.value) && !offeredPassword.value) {
        offeredPassword.value = true;
        usePassword.value = true;

        return;
      }

      const known = knownWithPassword.includes(email.value) || knownWithoutPassword.includes(email.value);

      if (!known && !needsName.value) {
        needsName.value = true;

        return;
      }

      state.value = 'sent';
    }

    function reset() {
      state.value = 'default';
      email.value = '';
      name.value = '';
      password.value = '';
      offeredPassword.value = false;
    }

    const chainRows = [
      { id: 'u2', marks: ['yes', 'yes', 'maybe'] },
      { id: 'u3', marks: ['yes', 'maybe', 'yes'] },
      { id: 'u4', marks: ['no', 'yes', 'yes'] },
      { id: 'u1', marks: ['yes', 'no', 'none'] }
    ];

    return { ui, state, email, name, password, needsName, usePassword, error, valid, submit, reset, go, t, chainRows };
  },
  template: `
    <div>
      <header class="app-header welcome-header">
        <div class="container app-header__inner">
          <Wordmark />
          <span class="app-header__spacer"></span>
          <LangSwitch />
        </div>
      </header>

      <main class="container">
        <section class="hero">
          <div class="hero__copy">
            <h1 class="hero__title" v-html="t('welcome.title', { tag: '<span class=&quot;tag&quot;>' + t('welcome.tag') + '</span>' })"></h1>
            <p class="hero__lede">{{ t('welcome.lede') }}</p>

            <div class="chain" aria-hidden="true">
              <div class="chain__step">
                <span class="chain__label"><b>1</b> {{ t('welcome.chain.1') }}</span>
                <div class="mini">
                  <div class="mini__title">{{ t('welcome.mini.poll') }}</div>
                  <div class="mini-grid">
                    <span></span>
                    <span class="mini-grid__head">20.11.</span>
                    <span class="mini-grid__head mini-grid__head--best">21.11.</span>
                    <span class="mini-grid__head">27.11.</span>
                    <template v-for="row in chainRows" :key="row.id">
                      <Avatar :id="row.id" />
                      <Mark v-for="(m, i) in row.marks" :key="i" :answer="m" />
                    </template>
                  </div>
                </div>
              </div>
              <div class="chain__step">
                <span class="chain__label"><b>2</b> {{ t('welcome.chain.2') }}</span>
                <div class="mini mini-event">
                  <div class="mini__title">{{ t('welcome.mini.event') }}</div>
                  <div class="mini-event__date"><span class="tag">14.11.</span><span>18:00</span></div>
                  <div class="mini-event__people">
                    <span class="avatar-stack"><Avatar id="u2" /><Avatar id="u3" /><Avatar id="u4" /><Avatar id="u1" /></span>
                    <span>{{ t('welcome.mini.accepted') }}</span>
                  </div>
                </div>
              </div>
              <div class="chain__step">
                <span class="chain__label"><b>3</b> {{ t('welcome.chain.3') }}</span>
                <div class="mini">
                  <div class="mini__title">{{ t('welcome.mini.list') }}</div>
                  <div class="mini-list">
                    <div class="mini-list__item"><Mark answer="yes" /><span class="grow">PA</span><span class="mini-list__who">Lena</span></div>
                    <div class="mini-list__item"><Mark answer="yes" /><span class="grow">XLR 8×</span><span class="mini-list__who">4 + 2</span></div>
                    <div class="mini-list__item"><Mark answer="none" /><span class="grow">Merch table</span><span class="mini-list__who">?</span></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <form class="login-card" @submit.prevent="submit" data-feature="F-01" novalidate>
            <h2>{{ t('welcome.login.title') }}</h2>

            <div class="alert alert--warning" v-if="state === 'expired'" role="alert">
              <Icon name="clock" />
              <span class="alert__body">
                {{ t('welcome.login.expired') }}
                <button type="button" class="btn btn--soft btn--sm" @click="state = 'default'"><Icon name="mail" /> {{ t('welcome.login.requestNew') }}</button>
              </span>
            </div>

            <template v-if="state !== 'sent'">
              <div class="login-card__steps">
                <FormField :label="t('welcome.login.email')" control="login-email" required :error="error">
                  <input class="input" id="login-email" type="email" autocomplete="email" v-model.trim="email" :aria-invalid="error ? 'true' : null" placeholder="name@example.com">
                </FormField>

                <FormField v-if="needsName" :label="t('welcome.login.name')" control="login-name" required :hint="t('welcome.login.nameHint')">
                  <input class="input" id="login-name" type="text" autocomplete="name" v-model.trim="name">
                </FormField>

                <FormField v-if="usePassword" :label="t('welcome.login.password')" control="login-password" :hint="t('welcome.login.passwordHint')">
                  <input class="input" id="login-password" type="password" autocomplete="current-password" v-model="password">
                </FormField>
              </div>

              <div class="stack stack--tight">
                <button type="submit" class="btn btn--primary btn--block">
                  <Icon :name="usePassword ? 'check' : 'mail'" />
                  {{ usePassword ? t('welcome.login.withPassword') : (needsName ? t('welcome.login.continue') : t('welcome.login.sendLink')) }}
                </button>
                <button type="button" class="btn btn--ghost btn--block" v-if="usePassword" @click="usePassword = false; state = 'sent'">{{ t('welcome.login.useLink') }}</button>
              </div>

              <p class="login-card__note" v-if="!usePassword">{{ t('welcome.login.note') }}</p>
            </template>

            <template v-else>
              <div class="login-card__sent" role="status">
                <strong>{{ t('welcome.login.sentTitle') }}</strong>
                <span>{{ t('welcome.login.sentText', { email }) }}</span>
              </div>
              <div class="stack stack--tight">
                <button type="button" class="btn btn--primary btn--block" @click="go('dashboard')"><Icon name="chevron" /> {{ t('welcome.login.sentDemo') }}</button>
                <button type="button" class="btn btn--ghost btn--block" @click="reset">{{ t('welcome.login.again') }}</button>
              </div>
            </template>
          </form>
        </section>

        <section class="steps" aria-label="How it works">
          <div class="step">
            <span class="step__num" aria-hidden="true">1</span>
            <h3>{{ t('welcome.steps.1.title') }}</h3>
            <p>{{ t('welcome.steps.1.text') }}</p>
          </div>
          <div class="step">
            <span class="step__num" aria-hidden="true">2</span>
            <h3>{{ t('welcome.steps.2.title') }}</h3>
            <p>{{ t('welcome.steps.2.text') }}</p>
          </div>
          <div class="step">
            <span class="step__num" aria-hidden="true">3</span>
            <h3>{{ t('welcome.steps.3.title') }}</h3>
            <p>{{ t('welcome.steps.3.text') }}</p>
          </div>
        </section>

        <footer class="site-footer">
          <span>© 2026 {{ t('welcome.footer.copy') }}</span>
          <nav>
            <a href="#privacy">{{ t('welcome.footer.privacy') }}</a>
            <a href="#legal">{{ t('welcome.footer.legal') }}</a>
          </nav>
          <LangSwitch />
        </footer>
      </main>
    </div>`
};

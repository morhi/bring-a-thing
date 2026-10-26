// Settings page (F-02, F-24): name, optional password, interface language, logout.

import { ref, reactive, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, currentUser, go, viewState, toast } from '../core.js';
import { t } from '../i18n.js';

const MIN_PASSWORD_LENGTH = 8;

export const SettingsView = {
  setup() {
    const state = viewState('settings');
    const user = currentUser;
    const profile = reactive({ name: user.value.name });
    const profileError = ref('');
    const password = reactive({ current: '', next: '', repeat: '' });
    const passwordErrors = reactive({ current: '', next: '', repeat: '' });

    // The review state shows the set-password variant even for the account that already has one.
    const hasPassword = computed(() => state.value !== 'noPassword' && !!user.value.hasPassword);

    const languageOptions = computed(() => [
      { value: 'en', title: 'English', hint: t('settings.language.enHint') },
      { value: 'de', title: 'Deutsch', hint: t('settings.language.deHint') }
    ]);

    // The role switcher swaps the account under the form, so the form follows it.
    watch(() => user.value.id, () => {
      profile.name = user.value.name;
      profileError.value = '';
    });

    function saveProfile() {
      const name = profile.name.trim();

      if (!name) {
        profileError.value = t('settings.nameRequired');

        return;
      }

      user.value.name = name;
      profile.name = name;
      profileError.value = '';
      toast(t('settings.saved'));
    }

    function savePassword() {
      passwordErrors.current = hasPassword.value && !password.current ? t('settings.password.currentRequired') : '';
      passwordErrors.next = password.next.length < MIN_PASSWORD_LENGTH ? t('settings.password.tooShort') : '';
      passwordErrors.repeat = !passwordErrors.next && password.repeat !== password.next ? t('settings.password.mismatch') : '';

      if (passwordErrors.current || passwordErrors.next || passwordErrors.repeat) {
        return;
      }

      const changed = hasPassword.value;
      user.value.hasPassword = true;
      state.value = 'default';
      Object.assign(password, { current: '', next: '', repeat: '' });
      toast(t(changed ? 'settings.password.changeDone' : 'settings.password.setDone'));
    }

    // The choice is stored on the account so other devices and the login email follow it (F-24).
    function setLanguage(lang) {
      ui.lang = lang;
      user.value.language = lang;
    }

    return { ui, user, profile, profileError, password, passwordErrors, hasPassword, languageOptions, saveProfile, savePassword, setLanguage, go, t };
  },
  template: `
    <main class="container page settings-page">
      <div class="settings-head">
        <button type="button" class="back" @click="go('dashboard')"><Icon name="back" /> {{ t('nav.backToDashboard') }}</button>
        <h1>{{ t('settings.title') }}</h1>
        <p class="lede">{{ t('settings.lede') }}</p>
      </div>

      <section class="settings-section" aria-labelledby="settings-account-title" data-feature="F-02">
        <div class="settings-section__intro">
          <h2 id="settings-account-title">{{ t('settings.account') }}</h2>
          <p>{{ t('settings.account.text') }}</p>
        </div>
        <form class="card settings-card" @submit.prevent="saveProfile" novalidate>
          <div class="settings-identity">
            <Avatar :id="user.id" size="lg" />
            <span class="settings-identity__text">
              <span class="settings-identity__name">{{ user.name || t('common.noName') }}</span>
              <span class="settings-identity__email">{{ user.email }}</span>
            </span>
          </div>
          <FormField :label="t('settings.name')" control="settings-name" required :error="profileError">
            <input class="input" id="settings-name" v-model="profile.name" autocomplete="name" :aria-invalid="profileError ? 'true' : null">
          </FormField>
          <FormField :label="t('settings.email')" control="settings-email" :hint="t('settings.emailHint')">
            <input class="input" id="settings-email" type="email" :value="user.email" readonly>
          </FormField>
          <div class="settings-actions">
            <button type="submit" class="btn btn--primary">{{ t('settings.save') }}</button>
          </div>
        </form>
      </section>

      <section class="settings-section" aria-labelledby="settings-password-title" data-feature="F-02">
        <div class="settings-section__intro">
          <h2 id="settings-password-title">{{ t('settings.password') }}</h2>
          <p>{{ hasPassword ? t('settings.password.textSet') : t('settings.password.textNone') }}</p>
        </div>
        <form class="card settings-card" @submit.prevent="savePassword" novalidate>
          <FormField v-if="hasPassword" :label="t('settings.password.current')" control="settings-password-current" required :error="passwordErrors.current">
            <input class="input" id="settings-password-current" type="password" autocomplete="current-password" v-model="password.current" :aria-invalid="passwordErrors.current ? 'true' : null">
          </FormField>
          <FormField :label="t('settings.password.new')" control="settings-password-new" required :hint="t('settings.password.hint')" :error="passwordErrors.next">
            <input class="input" id="settings-password-new" type="password" autocomplete="new-password" v-model="password.next" :aria-invalid="passwordErrors.next ? 'true' : null">
          </FormField>
          <FormField :label="t('settings.password.repeat')" control="settings-password-repeat" required :error="passwordErrors.repeat">
            <input class="input" id="settings-password-repeat" type="password" autocomplete="new-password" v-model="password.repeat" :aria-invalid="passwordErrors.repeat ? 'true' : null">
          </FormField>
          <div class="settings-actions">
            <button type="submit" class="btn btn--secondary">{{ hasPassword ? t('settings.password.change') : t('settings.password.set') }}</button>
          </div>
        </form>
      </section>

      <section class="settings-section" aria-labelledby="settings-language-title" data-feature="F-24">
        <div class="settings-section__intro">
          <h2 id="settings-language-title">{{ t('settings.language') }}</h2>
          <p>{{ t('settings.language.text') }}</p>
        </div>
        <div class="card settings-card">
          <FormField :label="t('settings.language.label')" group :hint="t('settings.language.note')">
            <ChoiceGroup :modelValue="ui.lang" name="settings-language" :options="languageOptions" @update:modelValue="setLanguage" />
          </FormField>
        </div>
      </section>

      <section class="settings-section" aria-labelledby="settings-logout-title" data-feature="F-02">
        <div class="settings-section__intro">
          <h2 id="settings-logout-title">{{ t('settings.logout') }}</h2>
          <p>{{ t('settings.logout.text') }}</p>
        </div>
        <div class="card settings-card">
          <div class="settings-actions">
            <button type="button" class="btn btn--secondary" @click="go('welcome')"><Icon name="logout" /> {{ t('nav.logout') }}</button>
          </div>
        </div>
      </section>
    </main>`
};

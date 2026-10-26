import { createApp, computed, watch } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, views, toast } from './core.js';
import { t } from './i18n.js';
import { Icon, Avatar, Mark, DateBlock, EmptyState, AvatarStack, AppDialog, LangSwitch, FormField, ChoiceGroup, ProgressBar, FormPage } from './components.js';
import { Wordmark, AppHeader, Toasts, ReviewBar } from './shell.js';
import { WelcomeView } from './views/welcome.js';
import { DashboardView } from './views/dashboard.js';
import { GroupView } from './views/group.js';
import { StubView } from './dialogs.js';
import { CreatePollView, CreateEventView, CreateListView } from './views/create.js';
import { OptionCalendar } from './option-calendar.js';
import { NewGroupView } from './views/new-group.js'; // new-group
import { InviteField } from './invite-field.js'; // new-group
import { EventView } from './views/event.js'; // event
import { PollView } from './views/poll.js'; // poll
import { ListView } from './views/list.js'; // list
import { SettingsView } from './views/settings.js'; // settings
import { FriendsView } from './views/friends.js'; // friends
import { NotificationsBell } from './notifications.js'; // notifications
import { GroupSettingsView } from './views/group-settings.js'; // group-settings
import { JoinView } from './views/join.js'; // invite-link

const App = {
  setup() {
    const view = computed(() => views[ui.view] || views.dashboard);

    // F-21: the note stays until the connection is back, then the app confirms it is current again
    watch(() => ui.offline, (offline) => {
      if (!offline) {
        toast(t('conn.restored'));
      }
    });

    return { ui, view, t };
  },
  template: `
    <div :class="{ 'show-features': ui.showFeatures }">
      <div class="alert alert--warning connection-note" v-if="ui.offline" role="status" data-feature="F-21">
        <Icon name="clock" />
        <span class="alert__body">{{ t('conn.lost') }}</span>
      </div>
      <AppHeader v-if="!view.public" />
      <component :is="view.component" :key="ui.view" />
      <Toasts />
      <ReviewBar />
    </div>`
};

const app = createApp(App);

Object.entries({
  Icon, Avatar, Mark, DateBlock, EmptyState, AvatarStack, AppDialog, LangSwitch, FormField, ChoiceGroup, ProgressBar, FormPage,
  Wordmark, AppHeader, Toasts, ReviewBar,
  WelcomeView, DashboardView, GroupView, StubView, CreatePollView, CreateEventView, CreateListView,
  OptionCalendar,
  NewGroupView, InviteField, // new-group
  EventView, // event
  PollView, // poll
  ListView, // list
  SettingsView, // settings
  FriendsView, // friends
  NotificationsBell, // notifications
  GroupSettingsView, // group-settings
  JoinView // invite-link
}).forEach(([name, component]) => app.component(name, component));

app.mount('#app');

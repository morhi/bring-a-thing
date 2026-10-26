import { createApp, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, views } from './core.js';
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

    return { ui, view };
  },
  template: `
    <div :class="{ 'show-features': ui.showFeatures }">
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

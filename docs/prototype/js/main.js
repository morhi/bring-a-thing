import { createApp, computed } from 'https://unpkg.com/vue@3.5.13/dist/vue.esm-browser.prod.js';
import { ui, views } from './core.js';
import { Icon, Avatar, Mark, DateBlock, EmptyState, AvatarStack, AppDialog, LangSwitch, FormField, ChoiceGroup, ProgressBar, FormPage } from './components.js';
import { Wordmark, AppHeader, Toasts, ReviewBar } from './shell.js';
import { WelcomeView } from './views/welcome.js';
import { DashboardView } from './views/dashboard.js';
import { GroupView } from './views/group.js';
import { NewGroupDialog, StubView } from './dialogs.js';
import { CreatePollView, CreateEventView, CreateListView } from './views/create.js';
import { OptionCalendar } from './option-calendar.js';
import { EventView } from './views/event.js'; // event
import { PollView } from './views/poll.js'; // poll
import { ListView } from './views/list.js'; // list
import { SettingsView } from './views/settings.js'; // settings
import { FriendsView } from './views/friends.js'; // friends
import { NotificationsBell } from './notifications.js'; // notifications
import { GroupSettingsView } from './views/group-settings.js'; // group-settings

const dialogs = {
  newGroup: 'NewGroupDialog'
};

const App = {
  setup() {
    const view = computed(() => views[ui.view] || views.dashboard);
    const dialogComponent = computed(() => (ui.dialog ? dialogs[ui.dialog.name] : null));

    return { ui, view, dialogComponent };
  },
  template: `
    <div :class="{ 'show-features': ui.showFeatures }">
      <AppHeader v-if="!view.public" />
      <component :is="view.component" :key="ui.view" />
      <component v-if="dialogComponent" :is="dialogComponent" v-bind="ui.dialog.props" />
      <Toasts />
      <ReviewBar />
    </div>`
};

const app = createApp(App);

Object.entries({
  Icon, Avatar, Mark, DateBlock, EmptyState, AvatarStack, AppDialog, LangSwitch, FormField, ChoiceGroup, ProgressBar, FormPage,
  Wordmark, AppHeader, Toasts, ReviewBar,
  WelcomeView, DashboardView, GroupView, StubView, CreatePollView, CreateEventView, CreateListView,
  NewGroupDialog, OptionCalendar,
  EventView, // event
  PollView, // poll
  ListView, // list
  SettingsView, // settings
  FriendsView, // friends
  NotificationsBell, // notifications
  GroupSettingsView // group-settings
}).forEach(([name, component]) => app.component(name, component));

app.mount('#app');

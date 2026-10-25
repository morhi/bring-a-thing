// Interface strings in English and German (F-24). User-written content is never translated.

import { ui } from './core.js';

const en = {
  // Shell
  'nav.dashboard': 'Dashboard',
  'nav.settings': 'Settings',
  'nav.friends': 'Friends',
  'nav.logout': 'Log out',
  'nav.notifications': 'Notifications',
  'nav.back': 'Back',
  'nav.backToDashboard': 'Back to dashboard',
  'nav.menu': 'Account menu',

  // Start page
  'welcome.title': 'Find the date. Fix it. Then bring a {tag}.',
  'welcome.tag': 'thing',
  'welcome.lede': 'Bring a Thing helps bands, clubs and groups of friends pick a date together, keep the follow-ups to the people who said yes, and sort out who brings what.',
  'welcome.chain.1': 'Find a date',
  'welcome.chain.2': 'Fix it',
  'welcome.chain.3': 'Bring a thing',
  'welcome.mini.poll': 'Herbstkonzert',
  'welcome.mini.best': 'Best',
  'welcome.mini.event': 'Hochzeit Lena & Tobi',
  'welcome.mini.accepted': '4 accepted',
  'welcome.mini.list': 'Equipment & setup',
  'welcome.login.title': 'Log in or sign up',
  'welcome.login.email': 'Email',
  'welcome.login.name': 'Your name',
  'welcome.login.nameHint': 'New here? Tell the others who you are.',
  'welcome.login.password': 'Password',
  'welcome.login.passwordHint': 'You set a password earlier. You can still use the email link instead.',
  'welcome.login.sendLink': 'Send me a login link',
  'welcome.login.continue': 'Continue',
  'welcome.login.withPassword': 'Log in with password',
  'welcome.login.useLink': 'Use the email link instead',
  'welcome.login.note': 'No password needed. We email you a link that logs you in.',
  'welcome.login.sentTitle': 'Check your inbox',
  'welcome.login.sentText': 'We sent a login link to {email}. It expires in 15 minutes.',
  'welcome.login.sentDemo': 'Open the link (prototype)',
  'welcome.login.again': 'Use a different email',
  'welcome.login.invalid': 'That does not look like an email address.',
  'welcome.steps.1.title': 'Ask the group for dates',
  'welcome.steps.1.text': 'Propose days or time slots. Everyone answers yes, maybe or no, and the best options stand out.',
  'welcome.steps.2.title': 'Fix one date or several',
  'welcome.steps.2.text': 'Pick the dates that work. Each one becomes an event with the people who said yes already on board.',
  'welcome.steps.3.title': 'Plan with the people who are in',
  'welcome.steps.3.text': 'Rehearsals, shifts, snacks, cables. Follow-up polls and lists only reach the participants.',
  'welcome.footer.privacy': 'Privacy',
  'welcome.footer.legal': 'Legal notice',
  'welcome.footer.copy': 'Bring a Thing',
  'welcome.language': 'Language',

  // Dashboard
  'dash.greeting.morning': 'Good morning, {name}',
  'dash.greeting.day': 'Hello, {name}',
  'dash.greeting.evening': 'Good evening, {name}',
  'dash.waiting': 'Waiting for you',
  'dash.waiting.poll': 'Answer the poll',
  'dash.waiting.pollMeta': '{answered} of {total} options answered',
  'dash.waiting.pollMetaNone': 'No answer yet',
  'dash.waiting.event': 'Are you in?',
  'dash.waiting.eventMeta': '{accepted} accepted so far',
  'dash.waiting.done': 'All caught up. Nothing needs your answer right now.',
  'dash.upcoming': 'Upcoming events',
  'dash.upcoming.empty.title': 'No upcoming events',
  'dash.upcoming.empty.text': 'Events appear here once you accept one. Start with a poll or a single date in one of your groups.',
  'dash.claims': 'Your claims',
  'dash.claims.empty': 'You have not claimed anything for an upcoming list yet.',
  'dash.claims.for': 'for {list}',
  'dash.groups': 'Groups',
  'dash.groups.new': 'New group',
  'dash.groups.members': '{n} members',
  'dash.groups.openPolls': '{n} open polls',
  'dash.groups.upcoming': '{n} upcoming',
  'word.members': 'members',
  'word.openPolls': 'open polls',
  'word.upcoming': 'upcoming',
  'dash.groups.empty.title': 'Start with a group',
  'dash.groups.empty.text': 'A group is the circle of people you plan with. Invite them once and reuse the group for every poll, event and list.',
  'dash.lists': 'Your lists',
  'dash.lists.new': 'New list',
  'dash.lists.empty.title': 'No standalone lists',
  'dash.lists.empty.text': 'A standalone list works without a group. Share it by link and let anyone claim what they bring.',
  'dash.lists.claimed': '{done} of {total} claimed',
  'dash.lists.shared': 'Shared by link',
  'dash.empty.title': 'Welcome to Bring a Thing',
  'dash.empty.text': 'Two ways to start: create a group for the people you plan with, or a standalone list for a single occasion.',

  // Group
  'group.members': 'Members',
  'group.member.owner': 'Owner',
  'group.member.admin': 'Admin',
  'group.member.member': 'Member',
  'group.member.pending': 'Invitation pending',
  'group.member.you': 'you',
  'group.invite.title': 'Invite someone',
  'group.invite.placeholder': 'name@example.com',
  'group.invite.button': 'Invite',
  'group.invite.hint': 'They get a login link by email and appear here right away.',
  'group.invite.sent': 'Invited {email}',
  'group.invite.invalid': 'Enter a valid email address.',
  'group.invite.exists': '{name} is already a member.',
  'group.settings': 'Group settings',
  'group.new.poll': 'New poll',
  'group.new.event': 'New event',
  'group.new.list': 'New list',
  'group.polls': 'Polls',
  'group.polls.empty.title': 'No polls yet',
  'group.polls.empty.text': 'Propose a few dates and let the group answer.',
  'group.polls.open': 'Open',
  'group.polls.closed': 'Closed',
  'group.polls.fixed': 'Fixed',
  'group.polls.responses': '{n} of {total} answered',
  'group.polls.followUp': 'Follow-up for {event}',
  'group.polls.options': '{n} options',
  'group.polls.dateFinder': 'Date finder',
  'group.polls.attendance': 'Attendance',
  'group.events': 'Events',
  'group.events.upcoming': 'Upcoming',
  'group.events.past': 'Past',
  'group.events.empty.title': 'No events yet',
  'group.events.empty.text': 'An event is a fixed date. Create one directly or fix it from a poll.',
  'group.events.accepted': '{n} accepted',
  'group.events.pending': '{n} pending',
  'group.events.fromPoll': 'From a poll',
  'group.events.youAccepted': 'You are in',
  'group.events.youPending': 'Your answer is open',
  'group.events.youDeclined': 'You declined',
  'group.lists': 'Group lists',
  'group.lists.empty.title': 'No group lists',
  'group.lists.empty.text': 'Group lists are visible to every member, for example snacks for rehearsal nights.',
  'group.lists.eventLists': 'Lists for events live inside the event.',
  'group.stats.members': '{n} members',
  'group.stats.openPolls': '{n} open polls',
  'group.stats.upcoming': '{n} upcoming events',
  'group.memberNotice': 'Only the owner and admins can invite people.',
  'group.notFound': 'Not found',

  // Dialogs
  'dialog.close': 'Close',
  'dialog.cancel': 'Cancel',
  'dialog.create': 'Create',
  'dialog.required': 'Required',

  'newGroup.title': 'New group',
  'newGroup.sub': 'The circle of people you plan with.',
  'newGroup.name': 'Group name',
  'newGroup.namePlaceholder': 'Nordlicht, Turnverein Seebach, Klasse 4b',
  'newGroup.invites': 'Invite people now',
  'newGroup.invitesHint': 'Optional. One email per line. Everyone gets a login link.',
  'newGroup.create': 'Create group',
  'newGroup.created': 'Group created',
  'newGroup.nameRequired': 'Give the group a name.',

  'newList.title': 'New list',
  'newList.sub': 'Collect who brings or takes on what.',
  'newList.name': 'Title',
  'newList.namePlaceholder': 'Equipment & setup, Raclette evening, Kuchenbuffet',
  'newList.description': 'Description',
  'newList.descriptionPlaceholder': 'What is this list for? Anything people should know before claiming.',
  'newList.date': 'Date',
  'newList.dateHint': 'Optional. Items can carry their own date later.',
  'newList.where': 'Where does this list live?',
  'newList.standalone': 'Standalone',
  'newList.standaloneHint': 'Only you see it, until you share a link.',
  'newList.inGroup': 'In {group}',
  'newList.inGroupHint': 'Every member sees it.',
  'newList.inEvent': 'In the event {event}',
  'newList.inEventHint': 'Only participants see it.',
  'newList.membersCanAdd': 'Members can add items',
  'newList.membersCanAddHint': 'Otherwise only you, the group owner and admins add items.',
  'newList.create': 'Create list',
  'newList.created': 'List created',
  'newList.titleRequired': 'Give the list a title.',

  'newPoll.title': 'New poll',
  'newPoll.followUpTitle': 'Follow-up poll',
  'newPoll.sub': 'Propose dates and let the group answer yes, maybe or no.',
  'newPoll.audienceGroup': 'Everyone in {group} can answer.',
  'newPoll.audienceGroupCount': '{n} members',
  'newPoll.audienceEvent': 'Only people who accepted {event} can answer.',
  'newPoll.audienceEventCount': '{n} accepted',
  'newPoll.name': 'Title',
  'newPoll.namePlaceholder': 'Herbstkonzert, Rehearsals in November, Band week',
  'newPoll.type': 'What do you want to find out?',
  'newPoll.type.dateFinder': 'Date finder',
  'newPoll.type.dateFinderHint': 'Converge on one or more dates. You fix the result.',
  'newPoll.type.attendance': 'Attendance',
  'newPoll.type.attendanceHint': 'See who is around on each day. Stays open.',
  'newPoll.mode': 'Granularity',
  'newPoll.mode.day': 'Whole days',
  'newPoll.mode.dayHint': 'One option per date.',
  'newPoll.mode.slots': 'Time slots',
  'newPoll.mode.slotsHint': 'Date, start and end. Several per day possible.',
  'newPoll.options': 'Options',
  'newPoll.selected': 'Selected options ({n})',
  'newPoll.noOptions': 'Nothing selected yet. Use the calendar above or add an option by hand.',
  'cal.prev': 'Previous',
  'cal.next': 'Next',
  'cal.hintDay': 'Click a day or drag across several days to select them.',
  'cal.hintSlots': 'Drag inside a day to draw a slot. Drag a slot to move it, or its edges to change start and end. Snaps to 15 minutes.',
  'cal.slotTitle': 'Drag to move, drag the edges to resize',
  'newPoll.addOption': 'Add option',
  'newPoll.removeOption': 'Remove option',
  'newPoll.slotLabel': 'Label (optional)',
  'newPoll.slotLabelPlaceholder': 'Lunch, Evening',
  'newPoll.start': 'Start',
  'newPoll.end': 'End',
  'newPoll.create': 'Create poll',
  'newPoll.created': 'Poll created',
  'newPoll.titleRequired': 'Give the poll a title.',
  'newPoll.optionsRequired': 'Add at least one option with a date.',
  'newPoll.modeLocked': 'Type and granularity cannot change after creation.',

  'newEvent.title': 'New event',
  'newEvent.sub': 'A fixed date. Everyone in the group is asked to accept or decline.',
  'newEvent.name': 'Title',
  'newEvent.namePlaceholder': 'Probe, Stadtfest Winterthur, Helfereinsatz',
  'newEvent.date': 'Date',
  'newEvent.time': 'Time',
  'newEvent.timeHint': 'Optional',
  'newEvent.end': 'End',
  'newEvent.location': 'Location',
  'newEvent.locationPlaceholder': 'Proberaum Zürich-Altstetten',
  'newEvent.description': 'Description',
  'newEvent.descriptionPlaceholder': 'What happens, what to bring, when to be there.',
  'newEvent.audience': '{n} members will be asked. You are in automatically.',
  'newEvent.create': 'Create event',
  'newEvent.created': 'Event created',
  'newEvent.titleRequired': 'Give the event a title.',
  'newEvent.dateRequired': 'Pick a date.',

  // Stubs
  'stub.standard': 'Standard page',
  'stub.settings.title': 'Settings',
  'stub.settings.text': 'Name, optional password, interface language and logout. Behaves like any account settings page.',
  'stub.friends.title': 'Friends',
  'stub.friends.text': 'Everyone you have invited, plus people you add by email. Used for suggestions when inviting.',
  'stub.next.title': 'Coming in the next batch',
  'stub.next.text': 'This page is not part of batch 1. Batch 2 covers the poll grid, the event page with participation, and the list with claims.',

  // Common
  'common.today': 'Today',
  'common.tomorrow': 'Tomorrow',
  'common.inDays': 'In {n} days',
  'common.daysAgo': '{n} days ago',
  'common.at': 'at',
  'common.you': 'You',
  'common.and': 'and',
  'common.more': '+{n}',
  'common.noName': 'No name yet',
  'common.optional': 'Optional',
  'common.open': 'Open',
  'common.notifications.unread': '{n} unread notifications',

  // Review bar
  'review.title': 'Review',
  'review.open': 'Review',
  'review.role': 'Viewing as',
  'review.state': 'View state',
  'review.features': 'Show feature IDs',
  'review.theme': 'Theme',
  'review.theme.system': 'System',
  'review.theme.light': 'Light',
  'review.theme.dark': 'Dark',
  'review.language': 'Language',
  'review.state.default': 'Default',
  'review.state.empty': 'Empty',
  'review.state.caughtUp': 'Nothing waiting',
  'review.state.sent': 'Link sent',
  'review.state.password': 'Has password',
  'review.state.error': 'Error',
  'review.state.noMembers': 'No other members'
};

const de = {
  'nav.dashboard': 'Übersicht',
  'nav.settings': 'Einstellungen',
  'nav.friends': 'Freunde',
  'nav.logout': 'Abmelden',
  'nav.notifications': 'Benachrichtigungen',
  'nav.back': 'Zurück',
  'nav.backToDashboard': 'Zurück zur Übersicht',
  'nav.menu': 'Kontomenü',

  'welcome.title': 'Termin finden. Festlegen. Dann ein {tag} mitbringen.',
  'welcome.tag': 'Thing',
  'welcome.lede': 'Bring a Thing hilft Bands, Vereinen und Freundeskreisen, gemeinsam einen Termin zu finden, Folgetermine nur mit den Zusagenden zu planen und zu klären, wer was mitbringt.',
  'welcome.chain.1': 'Termin finden',
  'welcome.chain.2': 'Festlegen',
  'welcome.chain.3': 'Mitbringen',
  'welcome.mini.poll': 'Herbstkonzert',
  'welcome.mini.best': 'Beste',
  'welcome.mini.event': 'Hochzeit Lena & Tobi',
  'welcome.mini.accepted': '4 Zusagen',
  'welcome.mini.list': 'Equipment & Aufbau',
  'welcome.login.title': 'Anmelden oder registrieren',
  'welcome.login.email': 'E-Mail',
  'welcome.login.name': 'Dein Name',
  'welcome.login.nameHint': 'Neu hier? Sag den anderen, wer du bist.',
  'welcome.login.password': 'Passwort',
  'welcome.login.passwordHint': 'Du hast ein Passwort gesetzt. Der Link per E-Mail geht weiterhin.',
  'welcome.login.sendLink': 'Anmeldelink schicken',
  'welcome.login.continue': 'Weiter',
  'welcome.login.withPassword': 'Mit Passwort anmelden',
  'welcome.login.useLink': 'Stattdessen Link per E-Mail',
  'welcome.login.note': 'Kein Passwort nötig. Wir schicken dir einen Link, der dich anmeldet.',
  'welcome.login.sentTitle': 'Schau in dein Postfach',
  'welcome.login.sentText': 'Wir haben einen Anmeldelink an {email} geschickt. Er gilt 15 Minuten.',
  'welcome.login.sentDemo': 'Link öffnen (Prototyp)',
  'welcome.login.again': 'Andere E-Mail verwenden',
  'welcome.login.invalid': 'Das sieht nicht wie eine E-Mail-Adresse aus.',
  'welcome.steps.1.title': 'Die Gruppe nach Terminen fragen',
  'welcome.steps.1.text': 'Schlag Tage oder Zeitfenster vor. Alle antworten mit Ja, Vielleicht oder Nein, und die besten Optionen stechen heraus.',
  'welcome.steps.2.title': 'Einen oder mehrere Termine festlegen',
  'welcome.steps.2.text': 'Wähl die Termine, die passen. Jeder wird ein Event, bei dem die Zusagenden schon an Bord sind.',
  'welcome.steps.3.title': 'Mit denen planen, die dabei sind',
  'welcome.steps.3.text': 'Proben, Schichten, Snacks, Kabel. Folgeumfragen und Listen erreichen nur die Teilnehmenden.',
  'welcome.footer.privacy': 'Datenschutz',
  'welcome.footer.legal': 'Impressum',
  'welcome.footer.copy': 'Bring a Thing',
  'welcome.language': 'Sprache',

  'dash.greeting.morning': 'Guten Morgen, {name}',
  'dash.greeting.day': 'Hallo, {name}',
  'dash.greeting.evening': 'Guten Abend, {name}',
  'dash.waiting': 'Wartet auf dich',
  'dash.waiting.poll': 'Umfrage beantworten',
  'dash.waiting.pollMeta': '{answered} von {total} Optionen beantwortet',
  'dash.waiting.pollMetaNone': 'Noch keine Antwort',
  'dash.waiting.event': 'Bist du dabei?',
  'dash.waiting.eventMeta': 'Bisher {accepted} Zusagen',
  'dash.waiting.done': 'Alles beantwortet. Gerade wartet nichts auf dich.',
  'dash.upcoming': 'Anstehende Events',
  'dash.upcoming.empty.title': 'Keine anstehenden Events',
  'dash.upcoming.empty.text': 'Events erscheinen hier, sobald du zusagst. Starte mit einer Umfrage oder einem Einzeltermin in einer Gruppe.',
  'dash.claims': 'Deine Übernahmen',
  'dash.claims.empty': 'Du hast für keine anstehende Liste etwas übernommen.',
  'dash.claims.for': 'für {list}',
  'dash.groups': 'Gruppen',
  'dash.groups.new': 'Neue Gruppe',
  'dash.groups.members': '{n} Mitglieder',
  'dash.groups.openPolls': '{n} offene Umfragen',
  'dash.groups.upcoming': '{n} anstehend',
  'word.members': 'Mitglieder',
  'word.openPolls': 'offene Umfragen',
  'word.upcoming': 'anstehend',
  'dash.groups.empty.title': 'Fang mit einer Gruppe an',
  'dash.groups.empty.text': 'Eine Gruppe ist der Kreis, mit dem du planst. Lade die Leute einmal ein und nutze die Gruppe für jede Umfrage, jedes Event und jede Liste.',
  'dash.lists': 'Deine Listen',
  'dash.lists.new': 'Neue Liste',
  'dash.lists.empty.title': 'Keine eigenständigen Listen',
  'dash.lists.empty.text': 'Eine eigenständige Liste braucht keine Gruppe. Teile sie per Link, und alle können übernehmen, was sie mitbringen.',
  'dash.lists.claimed': '{done} von {total} übernommen',
  'dash.lists.shared': 'Per Link geteilt',
  'dash.empty.title': 'Willkommen bei Bring a Thing',
  'dash.empty.text': 'Zwei Einstiege: eine Gruppe für die Leute, mit denen du planst, oder eine eigenständige Liste für einen einzelnen Anlass.',

  'group.members': 'Mitglieder',
  'group.member.owner': 'Owner',
  'group.member.admin': 'Admin',
  'group.member.member': 'Mitglied',
  'group.member.pending': 'Einladung offen',
  'group.member.you': 'du',
  'group.invite.title': 'Jemanden einladen',
  'group.invite.placeholder': 'name@beispiel.ch',
  'group.invite.button': 'Einladen',
  'group.invite.hint': 'Die Person erhält einen Anmeldelink per E-Mail und erscheint sofort hier.',
  'group.invite.sent': '{email} eingeladen',
  'group.invite.invalid': 'Gib eine gültige E-Mail-Adresse ein.',
  'group.invite.exists': '{name} ist schon Mitglied.',
  'group.settings': 'Gruppeneinstellungen',
  'group.new.poll': 'Neue Umfrage',
  'group.new.event': 'Neues Event',
  'group.new.list': 'Neue Liste',
  'group.polls': 'Umfragen',
  'group.polls.empty.title': 'Noch keine Umfragen',
  'group.polls.empty.text': 'Schlag ein paar Termine vor und lass die Gruppe antworten.',
  'group.polls.open': 'Offen',
  'group.polls.closed': 'Geschlossen',
  'group.polls.fixed': 'Festgelegt',
  'group.polls.responses': '{n} von {total} geantwortet',
  'group.polls.followUp': 'Folgeumfrage zu {event}',
  'group.polls.options': '{n} Optionen',
  'group.polls.dateFinder': 'Terminfinder',
  'group.polls.attendance': 'Anwesenheit',
  'group.events': 'Events',
  'group.events.upcoming': 'Anstehend',
  'group.events.past': 'Vergangen',
  'group.events.empty.title': 'Noch keine Events',
  'group.events.empty.text': 'Ein Event ist ein fester Termin. Leg eines direkt an oder leg es aus einer Umfrage fest.',
  'group.events.accepted': '{n} Zusagen',
  'group.events.pending': '{n} offen',
  'group.events.fromPoll': 'Aus einer Umfrage',
  'group.events.youAccepted': 'Du bist dabei',
  'group.events.youPending': 'Deine Antwort fehlt',
  'group.events.youDeclined': 'Du hast abgesagt',
  'group.lists': 'Gruppenlisten',
  'group.lists.empty.title': 'Keine Gruppenlisten',
  'group.lists.empty.text': 'Gruppenlisten sehen alle Mitglieder, zum Beispiel Snacks für Probeabende.',
  'group.lists.eventLists': 'Listen zu Events liegen im Event.',
  'group.stats.members': '{n} Mitglieder',
  'group.stats.openPolls': '{n} offene Umfragen',
  'group.stats.upcoming': '{n} anstehende Events',
  'group.memberNotice': 'Nur Owner und Admins können Leute einladen.',
  'group.notFound': 'Nicht gefunden',

  'dialog.close': 'Schliessen',
  'dialog.cancel': 'Abbrechen',
  'dialog.create': 'Anlegen',
  'dialog.required': 'Pflichtfeld',

  'newGroup.title': 'Neue Gruppe',
  'newGroup.sub': 'Der Kreis, mit dem du planst.',
  'newGroup.name': 'Gruppenname',
  'newGroup.namePlaceholder': 'Nordlicht, Turnverein Seebach, Klasse 4b',
  'newGroup.invites': 'Jetzt Leute einladen',
  'newGroup.invitesHint': 'Optional. Eine E-Mail pro Zeile. Alle erhalten einen Anmeldelink.',
  'newGroup.create': 'Gruppe anlegen',
  'newGroup.created': 'Gruppe angelegt',
  'newGroup.nameRequired': 'Gib der Gruppe einen Namen.',

  'newList.title': 'Neue Liste',
  'newList.sub': 'Sammeln, wer was mitbringt oder übernimmt.',
  'newList.name': 'Titel',
  'newList.namePlaceholder': 'Equipment & Aufbau, Raclette-Abend, Kuchenbuffet',
  'newList.description': 'Beschreibung',
  'newList.descriptionPlaceholder': 'Wofür ist die Liste? Alles, was man vor dem Übernehmen wissen sollte.',
  'newList.date': 'Datum',
  'newList.dateHint': 'Optional. Einträge können später ein eigenes Datum haben.',
  'newList.where': 'Wo gehört die Liste hin?',
  'newList.standalone': 'Eigenständig',
  'newList.standaloneHint': 'Nur du siehst sie, bis du einen Link teilst.',
  'newList.inGroup': 'In {group}',
  'newList.inGroupHint': 'Alle Mitglieder sehen sie.',
  'newList.inEvent': 'Im Event {event}',
  'newList.inEventHint': 'Nur Teilnehmende sehen sie.',
  'newList.membersCanAdd': 'Mitglieder dürfen Einträge hinzufügen',
  'newList.membersCanAddHint': 'Sonst nur du, der Gruppen-Owner und Admins.',
  'newList.create': 'Liste anlegen',
  'newList.created': 'Liste angelegt',
  'newList.titleRequired': 'Gib der Liste einen Titel.',

  'newPoll.title': 'Neue Umfrage',
  'newPoll.followUpTitle': 'Folgeumfrage',
  'newPoll.sub': 'Schlag Termine vor und lass die Gruppe mit Ja, Vielleicht oder Nein antworten.',
  'newPoll.audienceGroup': 'Alle in {group} können antworten.',
  'newPoll.audienceGroupCount': '{n} Mitglieder',
  'newPoll.audienceEvent': 'Nur wer bei {event} zugesagt hat, kann antworten.',
  'newPoll.audienceEventCount': '{n} Zusagen',
  'newPoll.name': 'Titel',
  'newPoll.namePlaceholder': 'Herbstkonzert, Proben im November, Bandwoche',
  'newPoll.type': 'Was willst du herausfinden?',
  'newPoll.type.dateFinder': 'Terminfinder',
  'newPoll.type.dateFinderHint': 'Auf einen oder mehrere Termine einigen. Du legst fest.',
  'newPoll.type.attendance': 'Anwesenheit',
  'newPoll.type.attendanceHint': 'Sehen, wer an welchem Tag da ist. Bleibt offen.',
  'newPoll.mode': 'Raster',
  'newPoll.mode.day': 'Ganze Tage',
  'newPoll.mode.dayHint': 'Eine Option pro Datum.',
  'newPoll.mode.slots': 'Zeitfenster',
  'newPoll.mode.slotsHint': 'Datum, Beginn und Ende. Mehrere pro Tag möglich.',
  'newPoll.options': 'Optionen',
  'newPoll.selected': 'Gewählte Optionen ({n})',
  'newPoll.noOptions': 'Noch nichts gewählt. Nutze den Kalender oben oder füge eine Option von Hand hinzu.',
  'cal.prev': 'Zurück',
  'cal.next': 'Weiter',
  'cal.hintDay': 'Klick auf einen Tag oder zieh über mehrere Tage, um sie auszuwählen.',
  'cal.hintSlots': 'Zieh in einem Tag, um ein Zeitfenster zu zeichnen. Zieh ein Fenster, um es zu verschieben, oder seine Kanten für Beginn und Ende. Rastet auf 15 Minuten ein.',
  'cal.slotTitle': 'Ziehen zum Verschieben, Kanten ziehen für Beginn und Ende',
  'newPoll.addOption': 'Option hinzufügen',
  'newPoll.removeOption': 'Option entfernen',
  'newPoll.slotLabel': 'Bezeichnung (optional)',
  'newPoll.slotLabelPlaceholder': 'Mittag, Abend',
  'newPoll.start': 'Beginn',
  'newPoll.end': 'Ende',
  'newPoll.create': 'Umfrage anlegen',
  'newPoll.created': 'Umfrage angelegt',
  'newPoll.titleRequired': 'Gib der Umfrage einen Titel.',
  'newPoll.optionsRequired': 'Füge mindestens eine Option mit Datum hinzu.',
  'newPoll.modeLocked': 'Art und Raster lassen sich nach dem Anlegen nicht mehr ändern.',

  'newEvent.title': 'Neues Event',
  'newEvent.sub': 'Ein fester Termin. Alle in der Gruppe werden um Zu- oder Absage gebeten.',
  'newEvent.name': 'Titel',
  'newEvent.namePlaceholder': 'Probe, Stadtfest Winterthur, Helfereinsatz',
  'newEvent.date': 'Datum',
  'newEvent.time': 'Uhrzeit',
  'newEvent.timeHint': 'Optional',
  'newEvent.end': 'Ende',
  'newEvent.location': 'Ort',
  'newEvent.locationPlaceholder': 'Proberaum Zürich-Altstetten',
  'newEvent.description': 'Beschreibung',
  'newEvent.descriptionPlaceholder': 'Was passiert, was mitbringen, wann da sein.',
  'newEvent.audience': '{n} Mitglieder werden gefragt. Du bist automatisch dabei.',
  'newEvent.create': 'Event anlegen',
  'newEvent.created': 'Event angelegt',
  'newEvent.titleRequired': 'Gib dem Event einen Titel.',
  'newEvent.dateRequired': 'Wähl ein Datum.',

  'stub.standard': 'Standardseite',
  'stub.settings.title': 'Einstellungen',
  'stub.settings.text': 'Name, optionales Passwort, Sprache der Oberfläche und Abmelden. Verhält sich wie jede Kontoeinstellungsseite.',
  'stub.friends.title': 'Freunde',
  'stub.friends.text': 'Alle, die du eingeladen hast, plus Personen, die du per E-Mail hinzufügst. Dienen als Vorschläge beim Einladen.',
  'stub.next.title': 'Kommt im nächsten Batch',
  'stub.next.text': 'Diese Seite gehört nicht zu Batch 1. Batch 2 umfasst das Antwortraster, die Event-Seite mit Teilnahme und die Liste mit Übernahmen.',

  'common.today': 'Heute',
  'common.tomorrow': 'Morgen',
  'common.inDays': 'In {n} Tagen',
  'common.daysAgo': 'Vor {n} Tagen',
  'common.at': 'um',
  'common.you': 'Du',
  'common.and': 'und',
  'common.more': '+{n}',
  'common.noName': 'Noch kein Name',
  'common.optional': 'Optional',
  'common.open': 'Öffnen',
  'common.notifications.unread': '{n} ungelesene Benachrichtigungen',

  'review.title': 'Review',
  'review.open': 'Review',
  'review.role': 'Ansicht als',
  'review.state': 'Zustand der Ansicht',
  'review.features': 'Feature-IDs anzeigen',
  'review.theme': 'Darstellung',
  'review.theme.system': 'System',
  'review.theme.light': 'Hell',
  'review.theme.dark': 'Dunkel',
  'review.language': 'Sprache',
  'review.state.default': 'Standard',
  'review.state.empty': 'Leer',
  'review.state.caughtUp': 'Nichts wartet',
  'review.state.sent': 'Link verschickt',
  'review.state.password': 'Mit Passwort',
  'review.state.error': 'Fehler',
  'review.state.noMembers': 'Keine weiteren Mitglieder'
};

const dictionaries = { en, de };

export function t(key, params = {}) {
  const dictionary = dictionaries[ui.lang] || en;
  let text = dictionary[key] ?? en[key] ?? key;

  Object.entries(params).forEach(([name, value]) => {
    text = text.replaceAll(`{${name}}`, String(value));
  });

  return text;
}

export const TODAY = new Date('2026-10-25T10:00:00');

function toDate(iso) {
  return new Date(`${iso}T00:00:00`);
}

export function formatDate(iso, style = 'medium') {
  if (!iso) {
    return '';
  }

  const date = toDate(iso);

  if (ui.lang === 'de') {
    if (style === 'short') {
      return date.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit' });
    }

    if (style === 'weekday') {
      return date.toLocaleDateString('de-CH', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    return date.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  if (style === 'short') {
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
  }

  if (style === 'weekday') {
    return date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }

  return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

export function formatLongDate(date = TODAY) {
  const locale = ui.lang === 'de' ? 'de-CH' : 'en-GB';

  return date.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' });
}

export function monthShort(iso) {
  const locale = ui.lang === 'de' ? 'de-CH' : 'en-GB';

  return toDate(iso).toLocaleDateString(locale, { month: 'short' }).replace('.', '');
}

export function dayNumber(iso) {
  return String(toDate(iso).getDate());
}

export function weekdayShort(iso) {
  const locale = ui.lang === 'de' ? 'de-CH' : 'en-GB';

  return toDate(iso).toLocaleDateString(locale, { weekday: 'short' }).replace('.', '');
}

export function relativeDay(iso) {
  const days = Math.round((toDate(iso) - toDate(TODAY.toISOString().slice(0, 10))) / 86400000);

  if (days === 0) {
    return t('common.today');
  }

  if (days === 1) {
    return t('common.tomorrow');
  }

  if (days > 1) {
    return t('common.inDays', { n: days });
  }

  return t('common.daysAgo', { n: Math.abs(days) });
}

export function formatTime(time) {
  return time || '';
}

export function isPast(iso) {
  return toDate(iso) < toDate(TODAY.toISOString().slice(0, 10));
}

// Mock data. Entity and field names follow requirements.md section 7.
// "Today" in the prototype is 2026-10-25.

export default {
  accounts: [
    // settings: u1 is the only account with a password, matching the demo accounts in welcome.js
    { id: 'u1', name: 'Maurice Hildebrandt', email: 'maurice@nordlicht.band', language: 'en', tone: 1, hasPassword: true },
    { id: 'u2', name: 'Lena Frei', email: 'lena.frei@gmail.com', language: 'de', tone: 2 },
    { id: 'u3', name: 'Tobias Keller', email: 'tobi.keller@bluewin.ch', language: 'de', tone: 3 },
    { id: 'u4', name: 'Sara Wyss', email: 'sara@wyss.ch', language: 'de', tone: 4 },
    { id: 'u5', name: '', email: 'jonas.buehler@gmx.ch', language: 'de', tone: 5 },
    { id: 'u6', name: 'Nina Roth', email: 'nina.roth@sunrise.ch', language: 'de', tone: 2 },
    { id: 'u7', name: 'Dario Meier', email: 'dario@tv-seebach.ch', language: 'de', tone: 3 },
    { id: 'u8', name: 'Mia Steiner', email: 'mia.steiner@hispeed.ch', language: 'de', tone: 4 }
  ],

  friends: [
    { accountId: 'u1', friendId: 'u2' },
    { accountId: 'u1', friendId: 'u3' },
    { accountId: 'u1', friendId: 'u4' },
    { accountId: 'u1', friendId: 'u5' },
    { accountId: 'u1', friendId: 'u6' },
    { accountId: 'u1', friendId: 'u7' },
    { accountId: 'u1', friendId: 'u8' },
    // friends: Lena and Tobias have their own circles, so the admin and member roles see a list too
    { accountId: 'u2', friendId: 'u1' },
    { accountId: 'u2', friendId: 'u3' },
    { accountId: 'u2', friendId: 'u5' },
    { accountId: 'u3', friendId: 'u1' },
    { accountId: 'u3', friendId: 'u2' }
  ],

  groups: [
    { id: 'g1', name: 'Nordlicht', ownerId: 'u1' },
    { id: 'g2', name: 'Turnverein Seebach', ownerId: 'u7' }
  ],

  memberships: [
    { groupId: 'g1', accountId: 'u1', role: 'owner', acceptedAt: '2026-03-02' },
    { groupId: 'g1', accountId: 'u2', role: 'admin', acceptedAt: '2026-03-02' },
    { groupId: 'g1', accountId: 'u3', role: 'member', acceptedAt: '2026-03-04' },
    { groupId: 'g1', accountId: 'u4', role: 'member', acceptedAt: '2026-03-09' },
    { groupId: 'g1', accountId: 'u6', role: 'member', acceptedAt: '2026-05-21' },
    { groupId: 'g1', accountId: 'u5', role: 'member', acceptedAt: null },
    { groupId: 'g2', accountId: 'u7', role: 'owner', acceptedAt: '2026-01-15' },
    { groupId: 'g2', accountId: 'u1', role: 'member', acceptedAt: '2026-01-20' },
    { groupId: 'g2', accountId: 'u4', role: 'member', acceptedAt: '2026-02-01' }
  ],

  polls: [
    { id: 'p1', groupId: 'g1', organizerId: 'u2', title: 'Herbstkonzert Kulturkeller', type: 'date_finder', mode: 'day', closedAt: null, eventId: null, createdAt: '2026-10-20' },
    { id: 'p2', groupId: 'g1', organizerId: 'u1', title: 'Rehearsals for the wedding set', type: 'date_finder', mode: 'slots', closedAt: null, eventId: 'e1', createdAt: '2026-10-22' },
    { id: 'p3', groupId: 'g1', organizerId: 'u1', title: 'Who is around in the band week?', type: 'attendance', mode: 'day', closedAt: null, eventId: null, createdAt: '2026-10-18' },
    { id: 'p4', groupId: 'g1', organizerId: 'u2', title: 'Hochzeit Lena & Tobi', type: 'date_finder', mode: 'day', closedAt: '2026-09-30', eventId: null, createdAt: '2026-09-14' },
    { id: 'p5', groupId: 'g2', organizerId: 'u7', title: 'Vereinsessen im Dezember', type: 'date_finder', mode: 'day', closedAt: null, eventId: null, createdAt: '2026-10-23' },
    // event: fixed follow-up poll of e1 that produced the child event e5
    { id: 'p6', groupId: 'g1', organizerId: 'u2', title: 'Soundcheck and setup', type: 'date_finder', mode: 'slots', closedAt: '2026-10-20', eventId: 'e1', createdAt: '2026-10-12' },
    // poll: closed attendance poll, open poll without answers, follow-up poll whose audience is only u3
    { id: 'p7', groupId: 'g1', organizerId: 'u1', title: 'Who is around in the autumn holidays?', type: 'attendance', mode: 'day', closedAt: '2026-10-09', eventId: null, createdAt: '2026-09-28' },
    { id: 'p8', groupId: 'g1', organizerId: 'u2', title: 'Christmas dinner', type: 'date_finder', mode: 'day', closedAt: null, eventId: null, createdAt: '2026-10-25' },
    { id: 'p9', groupId: 'g1', organizerId: 'u3', title: 'Warm-up jam before the rehearsal', type: 'date_finder', mode: 'slots', closedAt: null, eventId: 'e2', createdAt: '2026-10-24' },
    // list: attendance poll that the group list l6 and its items link to (F-18)
    { id: 'p10', groupId: 'g1', organizerId: 'u1', title: 'Weihnachtsmarkt: who is at the stand?', type: 'attendance', mode: 'day', closedAt: null, eventId: null, createdAt: '2026-10-24' }
  ],

  pollOptions: [
    { id: 'o1', pollId: 'p1', date: '2026-11-20', start: null, end: null, label: '', fixed: false },
    { id: 'o2', pollId: 'p1', date: '2026-11-21', start: null, end: null, label: '', fixed: false },
    { id: 'o3', pollId: 'p1', date: '2026-11-27', start: null, end: null, label: '', fixed: false },
    { id: 'o4', pollId: 'p1', date: '2026-11-28', start: null, end: null, label: '', fixed: false },

    { id: 'o5', pollId: 'p2', date: '2026-11-03', start: '19:30', end: '22:00', label: '', fixed: false },
    { id: 'o6', pollId: 'p2', date: '2026-11-05', start: '19:30', end: '22:00', label: '', fixed: false },
    { id: 'o7', pollId: 'p2', date: '2026-11-08', start: '14:00', end: '17:00', label: 'Afternoon', fixed: false },
    { id: 'o8', pollId: 'p2', date: '2026-11-10', start: '19:30', end: '22:00', label: '', fixed: false },
    { id: 'o9', pollId: 'p2', date: '2026-11-12', start: '19:30', end: '22:00', label: '', fixed: false },

    { id: 'o10', pollId: 'p3', date: '2026-11-23', start: null, end: null, label: '', fixed: false },
    { id: 'o11', pollId: 'p3', date: '2026-11-24', start: null, end: null, label: '', fixed: false },
    { id: 'o12', pollId: 'p3', date: '2026-11-25', start: null, end: null, label: '', fixed: false },
    { id: 'o13', pollId: 'p3', date: '2026-11-26', start: null, end: null, label: '', fixed: false },
    { id: 'o14', pollId: 'p3', date: '2026-11-27', start: null, end: null, label: '', fixed: false },

    { id: 'o15', pollId: 'p4', date: '2026-11-07', start: null, end: null, label: '', fixed: false },
    { id: 'o16', pollId: 'p4', date: '2026-11-14', start: null, end: null, label: '', fixed: true },
    { id: 'o17', pollId: 'p4', date: '2026-11-21', start: null, end: null, label: '', fixed: false },

    { id: 'o18', pollId: 'p5', date: '2026-12-04', start: null, end: null, label: '', fixed: false },
    { id: 'o19', pollId: 'p5', date: '2026-12-11', start: null, end: null, label: '', fixed: false },
    { id: 'o20', pollId: 'p5', date: '2026-12-18', start: null, end: null, label: '', fixed: false },

    // event
    { id: 'o21', pollId: 'p6', date: '2026-11-13', start: '18:00', end: '21:00', label: 'Setup', fixed: true },
    { id: 'o22', pollId: 'p6', date: '2026-11-14', start: '15:00', end: '17:00', label: 'Soundcheck', fixed: false },

    // poll
    { id: 'o41', pollId: 'p7', date: '2026-10-12', start: null, end: null, label: '', fixed: false },
    { id: 'o42', pollId: 'p7', date: '2026-10-13', start: null, end: null, label: '', fixed: false },
    { id: 'o43', pollId: 'p7', date: '2026-10-14', start: null, end: null, label: '', fixed: false },
    { id: 'o44', pollId: 'p7', date: '2026-10-15', start: null, end: null, label: '', fixed: false },
    { id: 'o45', pollId: 'p7', date: '2026-10-16', start: null, end: null, label: '', fixed: false },
    { id: 'o46', pollId: 'p8', date: '2026-12-12', start: null, end: null, label: '', fixed: false },
    { id: 'o47', pollId: 'p8', date: '2026-12-18', start: null, end: null, label: '', fixed: false },
    { id: 'o48', pollId: 'p8', date: '2026-12-19', start: null, end: null, label: '', fixed: false },
    { id: 'o49', pollId: 'p9', date: '2026-10-27', start: '18:00', end: '19:00', label: '', fixed: false },
    { id: 'o50', pollId: 'p9', date: '2026-10-28', start: '18:00', end: '19:00', label: 'Before dinner', fixed: false },

    // list
    { id: 'o61', pollId: 'p10', date: '2026-12-11', start: null, end: null, label: '', fixed: false },
    { id: 'o62', pollId: 'p10', date: '2026-12-12', start: null, end: null, label: '', fixed: false },
    { id: 'o63', pollId: 'p10', date: '2026-12-13', start: null, end: null, label: '', fixed: false }
  ],

  responses: [
    { optionId: 'o1', accountId: 'u1', answer: 'yes' },
    { optionId: 'o2', accountId: 'u1', answer: 'no' },
    { optionId: 'o1', accountId: 'u2', answer: 'yes' },
    { optionId: 'o2', accountId: 'u2', answer: 'yes' },
    { optionId: 'o3', accountId: 'u2', answer: 'maybe' },
    { optionId: 'o4', accountId: 'u2', answer: 'no' },
    { optionId: 'o1', accountId: 'u3', answer: 'yes' },
    { optionId: 'o2', accountId: 'u3', answer: 'maybe' },
    { optionId: 'o3', accountId: 'u3', answer: 'yes' },
    { optionId: 'o4', accountId: 'u3', answer: 'yes' },
    { optionId: 'o1', accountId: 'u4', answer: 'no' },
    { optionId: 'o2', accountId: 'u4', answer: 'yes' },
    { optionId: 'o3', accountId: 'u4', answer: 'yes' },
    { optionId: 'o4', accountId: 'u4', answer: 'maybe' },

    { optionId: 'o5', accountId: 'u2', answer: 'yes' },
    { optionId: 'o6', accountId: 'u2', answer: 'yes' },
    { optionId: 'o7', accountId: 'u2', answer: 'no' },
    { optionId: 'o8', accountId: 'u2', answer: 'yes' },
    { optionId: 'o9', accountId: 'u2', answer: 'maybe' },
    { optionId: 'o5', accountId: 'u3', answer: 'maybe' },
    { optionId: 'o6', accountId: 'u3', answer: 'yes' },
    { optionId: 'o7', accountId: 'u3', answer: 'yes' },
    { optionId: 'o8', accountId: 'u3', answer: 'yes' },
    { optionId: 'o9', accountId: 'u3', answer: 'no' },

    { optionId: 'o15', accountId: 'u1', answer: 'yes' },
    { optionId: 'o16', accountId: 'u1', answer: 'yes' },
    { optionId: 'o17', accountId: 'u1', answer: 'maybe' },
    { optionId: 'o15', accountId: 'u2', answer: 'no' },
    { optionId: 'o16', accountId: 'u2', answer: 'yes' },
    { optionId: 'o17', accountId: 'u2', answer: 'yes' },
    { optionId: 'o16', accountId: 'u3', answer: 'yes' },
    { optionId: 'o16', accountId: 'u4', answer: 'yes' },
    { optionId: 'o16', accountId: 'u6', answer: 'maybe' },

    { optionId: 'o18', accountId: 'u7', answer: 'yes' },
    { optionId: 'o19', accountId: 'u7', answer: 'yes' },
    { optionId: 'o20', accountId: 'u7', answer: 'no' },
    { optionId: 'o18', accountId: 'u4', answer: 'yes' },
    { optionId: 'o19', accountId: 'u4', answer: 'maybe' },

    // event
    { optionId: 'o21', accountId: 'u1', answer: 'yes' },
    { optionId: 'o22', accountId: 'u1', answer: 'maybe' },
    { optionId: 'o21', accountId: 'u2', answer: 'yes' },
    { optionId: 'o22', accountId: 'u2', answer: 'yes' },
    { optionId: 'o21', accountId: 'u3', answer: 'maybe' },
    { optionId: 'o22', accountId: 'u3', answer: 'yes' },
    { optionId: 'o21', accountId: 'u4', answer: 'yes' },

    // poll
    { optionId: 'o41', accountId: 'u1', answer: 'yes' },
    { optionId: 'o42', accountId: 'u1', answer: 'yes' },
    { optionId: 'o43', accountId: 'u1', answer: 'yes' },
    { optionId: 'o44', accountId: 'u1', answer: 'no' },
    { optionId: 'o45', accountId: 'u1', answer: 'no' },
    { optionId: 'o41', accountId: 'u2', answer: 'yes' },
    { optionId: 'o42', accountId: 'u2', answer: 'yes' },
    { optionId: 'o43', accountId: 'u2', answer: 'yes' },
    { optionId: 'o44', accountId: 'u2', answer: 'yes' },
    { optionId: 'o45', accountId: 'u2', answer: 'yes' },
    { optionId: 'o41', accountId: 'u3', answer: 'maybe' },
    { optionId: 'o42', accountId: 'u3', answer: 'yes' },
    { optionId: 'o43', accountId: 'u3', answer: 'yes' },
    { optionId: 'o44', accountId: 'u3', answer: 'no' },
    { optionId: 'o45', accountId: 'u3', answer: 'yes' },
    { optionId: 'o41', accountId: 'u4', answer: 'no' },
    { optionId: 'o42', accountId: 'u4', answer: 'no' },
    { optionId: 'o43', accountId: 'u4', answer: 'yes' },
    { optionId: 'o44', accountId: 'u4', answer: 'yes' },
    { optionId: 'o45', accountId: 'u4', answer: 'yes' },
    { optionId: 'o49', accountId: 'u3', answer: 'yes' },
    { optionId: 'o50', accountId: 'u3', answer: 'maybe' },

    // list: u1 said no for 12.12. and nothing for 13.12., so claiming those days shows the attendance warning
    { optionId: 'o61', accountId: 'u1', answer: 'yes' },
    { optionId: 'o62', accountId: 'u1', answer: 'no' },
    { optionId: 'o61', accountId: 'u2', answer: 'yes' },
    { optionId: 'o62', accountId: 'u2', answer: 'yes' },
    { optionId: 'o63', accountId: 'u2', answer: 'yes' },
    { optionId: 'o61', accountId: 'u3', answer: 'yes' },
    { optionId: 'o62', accountId: 'u3', answer: 'yes' },
    { optionId: 'o63', accountId: 'u3', answer: 'no' },
    { optionId: 'o62', accountId: 'u4', answer: 'yes' },
    { optionId: 'o63', accountId: 'u4', answer: 'yes' }
  ],

  events: [
    { id: 'e1', groupId: 'g1', organizerId: 'u2', title: 'Hochzeit Lena & Tobi', description: 'Two sets, 45 minutes each. Soundcheck at 16:00.', location: 'Gut Rheinau, Rheinau', start: '2026-11-14', startTime: '18:00', end: null, originOptionId: 'o16', parentEventId: null },
    { id: 'e2', groupId: 'g1', organizerId: 'u1', title: 'Probe', description: 'Run the new songs twice.', location: 'Proberaum Zürich-Altstetten', start: '2026-10-29', startTime: '19:30', end: null, originOptionId: null, parentEventId: null },
    { id: 'e3', groupId: 'g1', organizerId: 'u1', title: 'Stadtfest Winterthur', description: '', location: 'Neumarkt, Winterthur', start: '2026-09-12', startTime: '20:30', end: null, originOptionId: null, parentEventId: null },
    { id: 'e4', groupId: 'g2', organizerId: 'u7', title: 'Chilbi Helfereinsatz', description: 'Bar shift and clean-up.', location: 'Festzelt Seebach', start: '2026-11-21', startTime: '15:00', end: null, originOptionId: null, parentEventId: null },
    // event: child event of e1, fixed from the follow-up poll p6
    { id: 'e5', groupId: 'g1', organizerId: 'u2', title: 'Soundcheck and setup', description: 'Load in, build the stage, soundcheck.', location: 'Gut Rheinau, Rheinau', start: '2026-11-13', startTime: '18:00', end: '21:00', originOptionId: 'o21', parentEventId: 'e1' }
  ],

  participations: [
    { eventId: 'e1', accountId: 'u1', status: 'accepted' },
    { eventId: 'e1', accountId: 'u2', status: 'accepted' },
    { eventId: 'e1', accountId: 'u3', status: 'accepted' },
    { eventId: 'e1', accountId: 'u4', status: 'accepted' },
    { eventId: 'e1', accountId: 'u6', status: 'pending' },

    { eventId: 'e2', accountId: 'u1', status: 'accepted' },
    { eventId: 'e2', accountId: 'u2', status: 'pending' },
    { eventId: 'e2', accountId: 'u3', status: 'accepted' },
    { eventId: 'e2', accountId: 'u4', status: 'declined' },
    { eventId: 'e2', accountId: 'u6', status: 'pending' },
    { eventId: 'e2', accountId: 'u5', status: 'pending' },

    { eventId: 'e3', accountId: 'u1', status: 'accepted' },
    { eventId: 'e3', accountId: 'u2', status: 'accepted' },
    { eventId: 'e3', accountId: 'u3', status: 'accepted' },
    { eventId: 'e3', accountId: 'u4', status: 'accepted' },

    { eventId: 'e4', accountId: 'u7', status: 'accepted' },
    { eventId: 'e4', accountId: 'u1', status: 'accepted' },
    { eventId: 'e4', accountId: 'u4', status: 'pending' },

    // event
    { eventId: 'e5', accountId: 'u1', status: 'accepted' },
    { eventId: 'e5', accountId: 'u2', status: 'accepted' },
    { eventId: 'e5', accountId: 'u3', status: 'pending' },
    { eventId: 'e5', accountId: 'u4', status: 'accepted' }
  ],

  lists: [
    { id: 'l1', title: 'Equipment & setup', description: 'Everything we need on stage and at the merch table.', ownerId: 'u2', groupId: null, eventId: 'e1', date: null, membersCanAddItems: true, shareToken: null },
    { id: 'l2', title: 'Raclette evening', description: 'Bring what you can, I have the grill.', ownerId: 'u1', groupId: null, eventId: null, date: '2026-11-28', membersCanAddItems: true, shareToken: 'rcl-7f3a' },
    { id: 'l3', title: 'Kuchenbuffet Chilbi', description: '', ownerId: 'u7', groupId: 'g2', eventId: null, date: '2026-11-21', membersCanAddItems: true, shareToken: null },
    { id: 'l4', title: 'Proberaum snacks', description: 'Rotating list for rehearsal nights.', ownerId: 'u3', groupId: 'g1', eventId: null, date: null, membersCanAddItems: true, shareToken: null },
    // list: l5 is an event list where the admin is still pending (F-15); l6 is a group list with dated items, a custom field and the attendance link (F-14, F-16, F-18)
    { id: 'l5', title: 'Snacks für die Probe', description: 'Something to nibble on in the break.', ownerId: 'u3', groupId: null, eventId: 'e2', date: null, membersCanAddItems: false, shareToken: null },
    { id: 'l6', title: 'Weihnachtsmarkt Stand', description: 'Three days at the Christmas market. Claim a whole shift or part of the goods.', ownerId: 'u1', groupId: 'g1', eventId: null, date: '2026-12-11', membersCanAddItems: false, shareToken: null, attendanceOptionId: 'o61' }
  ],

  items: [
    { id: 'i1', listId: 'l1', name: 'PA system', quantity: null, unit: '', notes: 'The small one is enough for 80 people.' },
    { id: 'i2', listId: 'l1', name: 'Extension reel', quantity: null, unit: '', notes: '' },
    { id: 'i3', listId: 'l1', name: 'XLR cables', quantity: 8, unit: '', notes: '' },
    { id: 'i4', listId: 'l1', name: 'Drinks for the band', quantity: 2, unit: 'crates', notes: 'Water and one soft drink.' },
    { id: 'i5', listId: 'l1', name: 'Merch table', quantity: null, unit: '', notes: 'Foldable, plus tablecloth.' },
    { id: 'i6', listId: 'l1', name: 'Set lists printed', quantity: 5, unit: '', notes: '' },

    { id: 'i7', listId: 'l2', name: 'Raclette cheese', quantity: 2, unit: 'kg', notes: '' },
    { id: 'i8', listId: 'l2', name: 'Potatoes', quantity: 3, unit: 'kg', notes: 'Small, firm ones.' },
    { id: 'i9', listId: 'l2', name: 'Pickles and onions', quantity: null, unit: '', notes: '' },
    { id: 'i10', listId: 'l2', name: 'White wine', quantity: 4, unit: 'bottles', notes: '' },

    { id: 'i11', listId: 'l3', name: 'Zopf', quantity: 2, unit: '', notes: '' },
    { id: 'i12', listId: 'l3', name: 'Apfelkuchen', quantity: 3, unit: '', notes: '' },
    { id: 'i13', listId: 'l3', name: 'Schoggikuchen', quantity: 2, unit: '', notes: '' },

    { id: 'i14', listId: 'l4', name: 'Chips', quantity: 3, unit: 'bags', notes: '' },
    { id: 'i15', listId: 'l4', name: 'Beer', quantity: 12, unit: 'cans', notes: '' },

    // list
    { id: 'i16', listId: 'l5', name: 'Pretzels', quantity: 2, unit: 'bags', notes: '' },
    { id: 'i17', listId: 'l5', name: 'Mineral water', quantity: 6, unit: 'bottles', notes: 'Sparkling.' },

    { id: 'i18', listId: 'l6', name: 'Stand setup', quantity: null, unit: '', notes: 'From 14:00, the stand is delivered at 13:30.', date: null, attendanceOptionId: null, fieldValues: {} },
    { id: 'i19', listId: 'l6', name: 'Glühwein', quantity: 20, unit: 'l', notes: 'Red, not too sweet.', date: null, attendanceOptionId: null, fieldValues: { cf1: 'Alcohol' } },
    { id: 'i20', listId: 'l6', name: 'Zopf', quantity: 10, unit: '', notes: '', date: '2026-12-12', attendanceOptionId: 'o62', fieldValues: { cf1: 'Gluten, egg' } },
    { id: 'i21', listId: 'l6', name: 'Cups', quantity: 200, unit: '', notes: 'Reusable ones from the club.', date: '2026-12-12', attendanceOptionId: 'o62', fieldValues: {} },
    { id: 'i22', listId: 'l6', name: 'Clean-up', quantity: null, unit: '', notes: 'After closing at 18:00.', date: '2026-12-13', attendanceOptionId: 'o63', fieldValues: {} }
  ],

  claims: [
    { itemId: 'i1', accountId: 'u2', quantity: null },
    { itemId: 'i2', accountId: 'u1', quantity: null },
    { itemId: 'i3', accountId: 'u1', quantity: 4 },
    { itemId: 'i3', accountId: 'u3', quantity: 2 },
    { itemId: 'i4', accountId: 'u3', quantity: 1 },
    { itemId: 'i7', accountId: 'u1', quantity: 1 },
    { itemId: 'i7', accountId: 'u4', quantity: 1 },
    { itemId: 'i9', accountId: 'u6', quantity: null },
    { itemId: 'i10', accountId: 'u2', quantity: 2 },
    { itemId: 'i11', accountId: 'u7', quantity: 1 },
    { itemId: 'i12', accountId: 'u4', quantity: 1 },
    { itemId: 'i14', accountId: 'u3', quantity: 3 },

    // list
    { itemId: 'i17', accountId: 'u3', quantity: 2 },
    { itemId: 'i19', accountId: 'u2', quantity: 10 },
    { itemId: 'i21', accountId: 'u4', quantity: 200 }
  ],

  // notifications: every F-23 trigger appears for the owner (u1) or the member (u3); the admin (u2) has none, which shows the empty panel.
  // refId points at the group, poll, event, list or item the trigger concerns; actorId is the person who acted.
  notifications: [
    { id: 'n1', accountId: 'u1', type: 'new_poll', refId: 'p5', actorId: 'u7', readAt: null, at: '2026-10-23T18:40' },
    { id: 'n2', accountId: 'u1', type: 'new_event', refId: 'e4', actorId: 'u7', readAt: null, at: '2026-10-24T09:12' },
    { id: 'n3', accountId: 'u1', type: 'comment', refId: 'l1', actorId: 'u3', readAt: null, at: '2026-10-24T21:05' },
    { id: 'n4', accountId: 'u1', type: 'fixed', refId: 'e1', actorId: 'u2', readAt: '2026-10-01T08:00', at: '2026-09-30T20:15' },
    { id: 'n5', accountId: 'u1', type: 'invited', refId: 'g2', actorId: 'u7', readAt: '2026-01-20T09:30', at: '2026-01-15T17:05' },
    { id: 'n6', accountId: 'u3', type: 'new_poll', refId: 'p8', actorId: 'u2', readAt: null, at: '2026-10-25T08:30' },
    { id: 'n7', accountId: 'u3', type: 'claimed_for', refId: 'i3', actorId: 'u1', readAt: null, at: '2026-10-24T17:20' },
    { id: 'n8', accountId: 'u3', type: 'comment', refId: 'l1', actorId: 'u2', readAt: null, at: '2026-10-24T18:30' },
    { id: 'n9', accountId: 'u3', type: 'released', refId: 'i15', actorId: 'u1', readAt: '2026-10-11T07:45', at: '2026-10-10T21:10' },
    { id: 'n10', accountId: 'u3', type: 'fixed', refId: 'e1', actorId: 'u2', readAt: '2026-10-01T12:00', at: '2026-09-30T20:15' },
    { id: 'n11', accountId: 'u3', type: 'moved', refId: 'e3', actorId: 'u1', readAt: '2026-09-02T08:00', at: '2026-09-01T19:40' }
  ],

  // list
  customFields: [
    { id: 'cf1', listId: 'l6', name: 'Allergens' }
  ],

  // list: item comments carry the listId too, so deleting a list removes them in one pass
  comments: [
    { id: 'c1', listId: 'l1', itemId: null, authorId: 'u3', text: 'Soundcheck is at 16:00, so everything needs to be at the venue by 15:30.', at: '2026-10-24T21:05' },
    { id: 'c2', listId: 'l1', itemId: 'i4', authorId: 'u2', text: 'Is one crate of water enough for two sets?', at: '2026-10-24T18:30' },
    { id: 'c3', listId: 'l1', itemId: 'i4', authorId: 'u3', text: 'I bring a second one if nobody else does.', at: '2026-10-24T19:10' },
    { id: 'c4', listId: 'l2', itemId: null, authorId: 'u4', text: 'Can I bring a vegan cheese alternative as well?', at: '2026-10-23T12:40' }
  ]
};

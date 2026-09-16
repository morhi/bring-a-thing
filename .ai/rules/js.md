---
paths:
  - 'resources/js/**'
---

# Js

## Use the global toast system for flash feedback, not inline Message banners
Both `AppLayout.vue` and `GuestLayout.vue` render `<FlashToasts />` (resources/js/components/FlashToasts.vue), which watches `usePage().props.flash.success` / `.error` and shows a PrimeVue toast automatically. Pages must NOT render their own `<Message v-if="page.props.flash...">` banner for the same data — that duplicates the toast. A page only needs to read `flash` directly for something a toast can't express (rare). Any new controller action that flashes a message gets a toast for free through this mechanism; no per-page wiring needed.

## Prefix custom broadcastAs() event names with a dot in useEcho/Echo listeners
When a backend event overrides `broadcastAs()` with a custom name (e.g. `item.saved`, not the FQCN-derived default), every Echo listener for it (`useEcho`, `useEchoPresence`, `.listen()`, etc.) must prefix that name with a leading dot, e.g. `useEcho(channel, '.item.saved', cb)`. Without the dot, Echo silently prepends its default `App.Events` namespace and the callback never fires — no error, just a WS message that arrives and does nothing. Check every `app/Events/*.php` with a `broadcastAs()` against its frontend listener(s) when adding or reviewing real-time features.

## Every mutating request must surface its validation errors somewhere
Two valid patterns, pick based on whether there's a form:
1. Real forms: use `useForm()` and bind `form.errors.<field>` under each input (`:invalid="!!form.errors.x"` + `<small>{{ form.errors.x }}</small>`). Errors populate reactively; no extra wiring needed. See Login.vue, Rosters/Edit.vue.
2. Non-form/inline actions (buttons that call `router.post/patch/delete` directly, e.g. claim/unclaim, quick toggles): there is no errors bag bound to any input, so a validation failure (422) is otherwise silently swallowed — the button just does nothing with no feedback. These MUST pass an `onError` callback that shows a toast, e.g. `onError: (errors) => toast.add({ severity: 'error', summary: Object.values(errors)[0], life: 6000 })`. See RosterItemCard.vue's `showRequestError`.

Never assume "request completed" without checking that failure has a visible UI path — a request that changes nothing and shows nothing is a bug, not "it must have worked."

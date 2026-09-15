---
paths:
  - 'resources/js/**'
---

# Js

## Use the global toast system for flash feedback, not inline Message banners
Both `AppLayout.vue` and `GuestLayout.vue` render `<FlashToasts />` (resources/js/components/FlashToasts.vue), which watches `usePage().props.flash.success` / `.error` and shows a PrimeVue toast automatically. Pages must NOT render their own `<Message v-if="page.props.flash...">` banner for the same data — that duplicates the toast. A page only needs to read `flash` directly for something a toast can't express (rare). Any new controller action that flashes a message gets a toast for free through this mechanism; no per-page wiring needed.

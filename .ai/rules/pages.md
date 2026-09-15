---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Consistent UX: page width, back-navigation, and destructive confirmations
Content width lives in one place: `AppLayout.vue`'s `<main>` wraps `<slot />` in `mx-auto w-full max-w-7xl`. Pages must NOT add their own `mx-auto max-w-*` page-level wrapper — that duplicates layout concerns and produces inconsistent widths across pages (this was a real bug: Dashboard was full-width while Settings/Groups pages were capped at max-w-md/max-w-2xl). A page's root template div should just be `flex flex-col gap-*`, no width class.

Any page nested one level below a top-level entity page (edit/settings-style pages, e.g. `Groups/Edit.vue`) must show an explicit back-link to its parent entity at the top of the content (icon + parent name, `<ArrowLeft />` from `@primeicons/vue/arrow-left`), not rely solely on an in-form "Cancel" button — that was reported as unintuitive. Top-level pages (e.g. `Groups/Show.vue`) rely on the persistent app-name/logo link in `AppLayout.vue`'s header for going back to the dashboard.

Every destructive action (delete, remove-member, etc.) must confirm via PrimeVue's `useConfirm()` + the app-level `<ConfirmDialog />` (registered in both layouts, `ConfirmationService` in `resources/js/app.ts`) before firing the request — never wire a destructive button straight to the request. See `Groups/Edit.vue` (`confirmDestroy`) and `Groups/Show.vue` (`confirmRemove`) for the pattern.

## Narrow single-column forms still need their own max-w on the card
The max-w-7xl page width lives in AppLayout (see the other page-width rule), but PrimeVue's Password component (and similar full-width-by-default inputs) stretch to fill their container. A single-column form card (Settings' password card, Groups/Edit's name/danger-zone cards) must keep its own `max-w-md` on the card div itself so it doesn't stretch to the full page width and look broken. This is a component-level sizing choice, not the page-level `mx-auto max-w-*` wrapper the other rule forbids — the distinction is: page wrapper = forbidden, individual narrow card/form width = fine and often necessary.

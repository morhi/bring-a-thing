---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Consistent UX: page width, back-navigation, and destructive confirmations
Content width lives in one place: `AppLayout.vue`'s `<main>` wraps `<slot />` in `mx-auto w-full max-w-7xl`. Pages must NOT add their own page-level `max-w-*`/`mx-auto` wrapper around their whole root div — that duplicates layout concerns and produces inconsistent widths across pages. A page's root template div should just be `flex flex-col gap-*`, no width class.

Any page nested one level below a top-level entity page (edit/settings-style pages, e.g. `Groups/Edit.vue`) must show an explicit back-link to its parent entity at the top of the content (icon + parent name, `<ArrowLeft />` from `@primeicons/vue/arrow-left`), not rely solely on an in-form "Cancel" button — that was reported as unintuitive. Top-level pages (e.g. `Groups/Show.vue`) rely on the persistent app-name/logo link in `AppLayout.vue`'s header for going back to the dashboard.

Every destructive action (delete, remove-member, etc.) must confirm via the `AlertDialog` components (`resources/js/components/ui/alert-dialog`) before firing the request — never wire a destructive button straight to the request. See `Groups/Edit.vue` (`confirmRemove`) and `components/app/invite-link-card.vue` for the pattern.

## Section boxes span full width; only form controls get an inner max-w
A page's section "boxes" (the `bg-white dark:bg-surface-900 rounded-lg ... shadow-sm` card divs) must NOT get their own `max-w-*`; they should fill the full width of AppLayout's max-w-7xl container, like Dashboard's Groups list or Groups/Show's Members card.

Where a box wraps a narrow single-column form (Settings' password form, Groups/Edit's name form) or short danger-zone text, put `max-w-md` on the inner `<form>` or paragraph, not on the outer card div — this keeps the box itself full-width while keeping actual input controls (inputs stretch to fill their container) at a readable width.

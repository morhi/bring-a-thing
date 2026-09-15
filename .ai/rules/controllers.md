---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Flash a success/error message on every mutating redirect
Every controller action that creates, updates, or deletes something and redirects (store/update/destroy/etc.) must flash user feedback so the frontend toast system can show it: `->with('success', 'Thing done.')` or `->with('error', '...')` on the RedirectResponse. Validation failures don't need this — Inertia's error bag already surfaces those. The shared Inertia prop is `flash.success` / `flash.error` (see `HandleInertiaRequests`); there is no other flash key. Missing this was a real bug (group rename silently succeeded with no feedback) — write a Pest test asserting `assertSessionHas('success', '...')` for new mutating actions.

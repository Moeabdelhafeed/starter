---
name: vue-admin-ui-patterns
description: "Use whenever building or editing an admin-panel Vue page/component in this starter (Index.vue, *Table.vue, *CreateModal.vue, *EditModal.vue, *Filters.vue). Covers the mandatory PageHeader→Filters→BulkActions→Table→Modals page layout, the shared design-system components (PageHeader, Badge, EmptyState, FormField, BaseModal/ConfirmDialog, Toaster, Skeleton), the custom Teleport modal pattern (NEVER Radix/Shadcn Dialog), Inertia form conventions including the MANDATORY PUT/DELETE method-spoofing workaround for hosts that block those verbs, the shared ImageUpload/VideoUpload drag-and-drop components, sticky-actions table columns, the mandatory table/grid view toggle wiring, filter conventions, and the Inertia shared props available on every page (auth, locale, flash, feature flags). Trigger on: building a Vue page/table/modal/form, wiring file uploads, 'why did my PUT request white-screen', adding a view toggle. Do not use for Tailwind/RTL/responsive styling rules (see styling-rtl-responsive) or backend controller/trait patterns (see admin-feature-crud)."
metadata:
  author: project
---

# Vue Admin UI Patterns

## Page Structure

Every feature page follows this layout:
```
PageHeader (title + actions) → Filters → BulkActions → Table/Grid (with EmptyState) → Modals
```

The page shell is one container — the layout already supplies the background, page height and
top bar, so a page must not wrap itself in `min-h-[100dvh] bg-background`:
```vue
<div class="mx-auto flex w-full max-w-[1300px] flex-col gap-5 px-4 py-6 md:py-8 text-start">
```

```vue
<script setup>
import Default from '@/layouts/default.vue';
defineOptions({ layout: Default });
</script>
```

## Inertia Shared Props (HandleInertiaRequests)

Available on every page via `usePage().props`:
- `auth.user` — **exactly** `{ id, name, email, image }`, never the full model. Shipping the whole
  record put device ids, social links and OTP state into the payload of every request. Need another
  column on a page? Pass it from that page's controller, don't widen the shared prop.
- `auth.roles` — array of role names.
- `auth.permissions` — array of permission names.
- `locale` — `{ code, dir, name }`.
- `success` / `error` — flash messages (rendered by `Toaster`, see below).
- Feature flags: `app_users`, `app_guests`, `has_translations`, `has_notification_templates`,
  `has_pages`, `has_app_settings`, `has_dynamic_storage`, `has_activity_logs`, `is_local`,
  `is_testing`, `multi_session`.
- `auth_identifiers` — array of the login identifiers in use.
- `auth_fields` — `{ email: bool, phone: bool, username: bool }` — which fields are available.
- `translation_warnings` — per-feature count of rows missing a translation (cached 60s).

These are typed in `resources/js/types/index.d.ts`. Extend that file rather than casting to `any`.

## Modals — `BaseModal` / `ConfirmDialog`

**NEVER use Radix/Reka Dialog.** The project ships its own Teleport-based modal, and every modal
must go through it rather than re-implementing the shell:

```vue
<script setup lang="ts">
import { BaseModal } from '@/components/ui/modal';
</script>

<template>
    <BaseModal :open="isOpen" :title="t('edit_user')" size="md" :busy="form.processing" @close="close">
        <form id="user-form" @submit.prevent="submit" class="space-y-5"><!-- fields --></form>
        <template #footer>
            <Button variant="outline" @click="close">{{ t('cancel') }}</Button>
            <Button type="submit" form="user-form" :disabled="form.processing">{{ t('save') }}</Button>
        </template>
    </BaseModal>
</template>
```

`BaseModal` props: `open` (required), `title`, `size` (`sm` = max-w-md, `md` = lg, `lg` = 2xl,
`xl` = 4xl), `closeOnBackdrop`, `closeOnEscape`, `hideClose`, `busy`. Emits `close`. Slots:
default (body), `header`, `icon`, `footer`.

It supplies what a hand-rolled modal kept forgetting: `role="dialog"`, `aria-modal`,
`aria-labelledby`, Escape-to-close, a focus trap, focus restored to the trigger on close, body
scroll lock, a bottom-sheet layout under `sm`, and `prefers-reduced-motion` handling.

Confirmations use `ConfirmDialog` (props `open`, `title`, `message`, `warningText`, `confirmLabel`,
`cancelLabel`, `variant`, `icon`, `processing`, `errors`; emits `close`, `confirm`). The shared
destructive flows — `DeleteModal`, `ForceDeleteModal`, `RestoreModal`, `BulkDeleteModal`,
`BulkRestoreModal`, `BulkForceDeleteModal` — are thin wrappers over it and keep their original
props/emits. Never use `confirm()` or `alert()`.

## Shared UI components

Reach for these before writing markup; they are what keeps pages looking like one product:

| Component | Import | Use for |
|---|---|---|
| `PageHeader` | `@/components/ui/page-header` | The page's `<h1>` + description + `#actions` slot. Every page starts with one — the title does NOT live in `*Filters.vue`. |
| `Badge` | `@/components/ui/badge` | Status pills. Variants: `default`, `secondary`, `success`, `warning`, `destructive`, `info`, `outline`. Never hand-roll `bg-emerald-500/10 text-emerald-600`. |
| `EmptyState` | `@/components/ui/empty-state` | The `v-else` of every table and grid. `variant="no-results"` when filters are active, `variant="empty"` otherwise. |
| `FormField` | `@/components/ui/form-field` | Label + control + error + hint, with a generated `id` wired to `for`/`aria-describedby`/`aria-invalid`. The default slot is scoped: `#default="field"` → `v-bind="field"` on the control. |
| `Skeleton`, `TableSkeleton` | `@/components/ui/skeleton` | Loading placeholders. |
| `Toaster` + `useToast()` | `@/components/ui/toast`, `@/composables/useToast` | Mounted once in the layout; it already renders the `success`/`error` flash props. Call `useToast().success(msg)` for client-side notices. |
| `useTheme()` | `@/composables/useTheme` | Dark-mode toggle (the initial theme is applied in the Blade head to avoid a flash). |
| `useSidebar()` | `@/composables/useSidebar` | Sidebar collapsed/mobile state. |
| `useCommandPalette()` | `@/composables/useCommandPalette` | Open/close the global search palette (Cmd/Ctrl+K). `useCommandPaletteShortcut()` binds the key and is called once, in the layout. |

`Table`/`TableHead`/`TableCell` already carry the correct row height, weight and casing —
do not re-add `py-4 font-bold` overrides. `Button`'s `outline` variant, `Input`, `Textarea` and
`SelectTrigger` no longer set a shadow, so `shadow-none!` overrides are unnecessary.

## Forms

- Use `useForm()` from Inertia.
- File uploads: add `forceFormData: true`.
- **NEVER send a real `PUT`/`PATCH`/`DELETE` request — the production host (LiteSpeed/Apache) blocks those verbs and the request white-screens.** Always POST with method spoofing:
  - `useForm` update → `form.transform((d) => ({ ...d, _method: 'PUT' })).post(url, opts)` (or put `_method: 'PUT'` in the form fields and call `form.post`).
  - `useForm` delete → `form.transform((d) => ({ ...d, _method: 'DELETE' })).post(url, opts)`.
  - `router.put(url, data, opts)` → `router.post(url, { ...data, _method: 'PUT' }, opts)`.
  - `router.delete(url, { data, ...opts })` → `router.post(url, { ...data, _method: 'DELETE' }, opts)`.
  - The route stays `Route::put(...)` / `Route::delete(...)`; Laravel resolves it from `_method` (HTTP method override is enabled in the kernel). The shared `DeleteModal` / `ForceDeleteModal` already do this — reuse them for deletes.
- Every Inertia request MUST include `reset: ['{dataKey}', 'success', 'error', 'filters']`.
- Every request should include `preserveScroll: true` and `preserveState: true`.
- **A write keeps the scrolled list and glows the row.** The controller paginates with `->scrollPaginate(N)` (never a bare `paginate()` for a scroll prop) and returns `redirect()->back()->with('success', …)->with('highlight', $model->id)`; the table's rows carry `v-highlight="row.id"`. That's the whole contract — `app.ts` and `useHighlight.ts` do the rest (see CLAUDE.md "Lists survive a write").

## Media Uploads (MANDATORY)

NEVER write a raw `<input type="file">` for images or videos. ALWAYS use the shared dashed drag-and-drop component:

- **Images:** `@/components/ui/image-upload/ImageUpload.vue`
- **Videos:** `@/components/ui/video-upload/VideoUpload.vue` (thin wrapper presetting `accept="video/*"`, 20MB default)

Both share the same API — a dashed drop box with drag & drop, click-to-pick, live preview, size guard, and a remove (X) button:

```vue
<script setup>
import ImageUpload from '@/components/ui/image-upload/ImageUpload.vue';

const form = useForm({
    image: null,
    remove_image: false, // edit forms only — flags an existing image for deletion
    _method: 'PUT',
});
</script>

<template>
    <!-- Create: no existing image, no remove flag -->
    <ImageUpload v-model="form.image" :label="t('image')" :error="form.errors.image" />

    <!-- Edit: pass the saved image URL + bind the remove flag -->
    <ImageUpload
        v-model="form.image"
        v-model:removed="form.remove_image"
        :preview-url="model.image?.image_api || null"
        :label="t('image')"
        :error="form.errors.image"
    />
</template>
```

**Props:** `previewUrl`, `accept` (default `image/*`), `label`, `error`, `required`, `removable` (default `true` — set `false` for things that can't be removed, e.g. app logo/favicon), `shape` (`square` | `circle`), `maxSizeMb` (default 2; VideoUpload 20).
**Models:** `v-model` = the `File`, `v-model:removed` = the delete flag.

**Backend — handle removal in update controllers.** Validate `'remove_image' => ['nullable', 'boolean']` and:

```php
if ($request->hasFile('image')) {
    $model->saveImage($request->file('image'), 'folder');
} elseif ($request->boolean('remove_image')) {
    $model->deleteImage(); // from HasImage trait
}
```

For video use `VideoUpload` + a `remove_video` flag and the `HasVideo` trait's `saveVideo()` / `deleteVideo()`.

## Tables

- Use `<InfiniteScroll>` from Inertia for pagination.
- Checkbox select-all with computed get/set pattern.
- Status toggles: optimistic update with rollback on error.
- Action buttons: Edit (yellow), Delete (red).
- **Sticky actions column (MANDATORY):** the actions `<TableHead>` AND its row `<TableCell>` MUST carry the `sticky-actions` utility class so the actions stay pinned to the inline-end edge when a wide row scrolls horizontally. The utility lives in `resources/css/app.css` (`@utility sticky-actions` — sticky, `inset-inline-end: 0`, `bg-card`, leading border, RTL-safe). Append it, don't replace existing classes: `<TableHead class="py-4 font-bold sticky-actions">`, `<TableCell class="sticky-actions">`.

## Table / Grid View Toggle (MANDATORY)

Every feature `*Table.vue` component supports BOTH a table view and a grid (card) view, switchable by the user and persisted per-feature. When creating or editing a feature table, wire all of the following:

1. **`view` prop** on the `*Table.vue`: `view: { type: String, default: 'table' }`.
2. **Two branches in the template** — both MUST keep their own `<InfiniteScroll ... data="DATAKEY">` around the loop so infinite scroll works in either view:
   - `v-if="view === 'table'"` → the existing `<Table>` (with `sticky-actions`, see above).
   - `v-else` → the grid: `<InfiniteScroll v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" preserve-url data="DATAKEY">` containing one card per row. Card shell: `class="flex flex-col gap-4 rounded-3xl border bg-card p-5 transition-shadow hover:shadow-md"` plus the same highlight ring the table row uses (`isHighlighted(...)`). Layout: checkbox top-start, badges (trashed/verified/etc.) top-end, identity fields, then a footer `mt-auto flex items-center justify-between gap-2 border-t pt-4` holding the status toggle + action buttons. Reuse the EXACT same button classes and `emit(...)` calls as the table rows. Omit gracefully anything the feature lacks (no status toggle, no soft-deletes, etc.).
3. **Composable** `useViewMode` (`resources/js/composables/useViewMode.ts`): in the page, `const { view } = useViewMode('FEATURE_KEY');` — the key scopes the localStorage persistence (e.g. `'users'`, `'roles'`).
4. **Shared toggle** `ViewToggle` (`resources/js/components/Shared/ViewToggle.vue`): render `<ViewToggle v-model="view" />` in the page's action bar (group it with the create/export buttons; make the bar `flex-col ... sm:flex-row`). Pass `:view="view"` to the `*Table.vue`.
5. Translation keys `table_view` / `grid_view` already exist in `en.json` + `ar.json`.

Reference implementation: `components/user/UserTable.vue` + `pages/User/Index.vue`.

## Filters

- Local refs for each filter field.
- `router.get()` with `preserveState: true, preserveScroll: true`.
- Active filter chips with clear button.
- "Clear Filters" resets all and re-fetches.

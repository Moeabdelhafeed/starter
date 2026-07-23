---
name: vue-admin-ui-patterns
description: "Use whenever building or editing an admin-panel Vue page/component in this starter (Index.vue, *Table.vue, *CreateModal.vue, *EditModal.vue, *Filters.vue). Covers the mandatory Filters→BulkActions→Create→Table→Modals page layout, the custom Teleport modal pattern (NEVER Radix/Shadcn Dialog), Inertia form conventions including the MANDATORY PUT/DELETE method-spoofing workaround for hosts that block those verbs, the shared ImageUpload/VideoUpload drag-and-drop components, sticky-actions table columns, the mandatory table/grid view toggle wiring, filter conventions, and the Inertia shared props available on every page (auth, locale, flash, feature flags). Trigger on: building a Vue page/table/modal/form, wiring file uploads, 'why did my PUT request white-screen', adding a view toggle. Do not use for Tailwind/RTL/responsive styling rules (see styling-rtl-responsive) or backend controller/trait patterns (see admin-feature-crud)."
metadata:
  author: project
---

# Vue Admin UI Patterns

## Page Structure

Every feature page follows this layout:
```
Filters → BulkActions → Create Button → Table → Modals
```

```vue
<script setup>
import Default from '@/layouts/default.vue';
defineOptions({ layout: Default });
</script>
```

## Inertia Shared Props (HandleInertiaRequests)

Available on every page via `usePage().props`:
- `auth.user` — authenticated user with `image` relationship loaded.
- `auth.roles` — array of role names.
- `auth.permissions` — array of permission names.
- `locale` — `{ code, dir, name }`.
- `success` / `error` — flash messages.
- `app_users`, `has_translations`, `is_local` — feature flags.
- `auth_identifier` — current login identifier (`email`, `phone`, or `username`).
- `auth_fields` — `{ email: bool, phone: bool, username: bool }` — which fields are available.

## Modals — Custom Teleport Pattern

**NEVER use Radix/Shadcn Dialog.** Always use:
```vue
<Teleport to="body">
    <Transition enter-active-class="transition duration-200 ease-out" ...>
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg ... rounded-2xl bg-card p-6 shadow-xl text-start">
                    <!-- content -->
                </div>
            </div>
        </div>
    </Transition>
</Teleport>
```

Bulk delete MUST use `BulkDeleteModal` component — never `confirm()` or `alert()`.

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

---
name: dynamic-storage-media
description: "Use whenever working on the Dynamic Storage keyed-media feature in this starter: MediaItem/MediaFile models, the HasFile trait, POST/GET/DELETE /api/media, or the admin Media CMS. This is the media analog of the translations system — assets are looked up by (group, sub_group, key) triple instead of a translation string. Trigger on: 'dynamic storage', 'keyed media', 'media library', MediaItem, media group/sub_group/key questions, or the Nuxt useMedia composable. Gated by HAS_DYNAMIC_STORAGE env flag. Do not use for the per-model HasImage/HasVideo traits used on regular feature models — see admin-feature-crud's traits reference for those."
metadata:
  author: project
---

# Dynamic Storage (keyed media)

A media analog of the translation system: the frontend uploads an **image / video / file** tagged `group` + `sub_group` + `key` (e.g. `web` / `auth` / `login_image`) and fetches it back by the same triple. Gated by `HAS_DYNAMIC_STORAGE` (default `true`).

**Data model.** `MediaItem` (`media_items`) holds only `key`, `group`, `sub_group` (empty-string sentinel, never NULL), `type` (`image`|`video`|`file`). Unique `(key, group, sub_group)`. The actual file hangs off a polymorphic morph, one per type:
- `image` → **`HasImage`** (webp via `ImageUploadService`, blurhash, `image_api`).
- `video` → **`HasVideo`** (`video_api`).
- `file` → **`HasFile`** trait + **`MediaFile`** model (`media_files`, `file_api` + `name`/`size`) — mirrors `HasVideo`/`Video`.
- `MediaItem::saveMedia($file, $type)` persists the row first (morph FK needs the id), clears all three morphs (type may change on replace), then saves the matching one. `MediaItem::detectType($file)` infers type from mime. `MediaItem::toApi()` returns `{ type, url, blurhash?, name?, size? }`. `deleteMedia()` clears every morph before row delete. Files land in `storage/app/public/dynamic-media/{group}/{sub_group}/`.

**Env caps:** `MEDIA_MAX_IMAGE_KB` / `MEDIA_MAX_VIDEO_KB` / `MEDIA_MAX_FILE_KB` — per-kind upload size caps (KB). Defaults `2048` / `20480` / `10240`. Read via `config('dynamic-storage.*')`.

**Public API** (`routes/api.php`, under `throttle:api`, standard `X-API-TOKEN` + device headers):
- `POST /api/media` (multipart): `group` (nullable, `app`|`web`, default `app`), `sub_group` (**required** slug), `key` (**required** slug), `file` (**required**). Type inferred from mime; per-type size cap + mime whitelist enforced (422 keyed `file`). **Create or replace** at `(key, group, sub_group)` — re-POST the same triple swaps the asset (even to a different type, old morph + file deleted); a new sub_group makes a new row. Returns `{ group, sub_group, key, ...toApi() }`.
- `GET /api/media?group=web`: returns every asset in the group **nested by sub_group** — `{ group, media: { [subGroup]: { [key]: { type, url, ... } } } }`. Empty sub_group folds to `general`. No per-sub filtering (client fetches the whole group).
- `DELETE /api/media?group=web&sub_group=auth&key=login_image`: deletes the file **and the row** at `(key, group, sub_group)` — a real delete, unlike the admin CMS's "remove" (see below), which only detaches the file and keeps the row. 404 if no item matches; 422 (keyed `sub_group`/`key`) if either is missing/malformed.

**Admin CMS** (`/media`, permission `dynamic_storage`): view + filter (group / sub_group) + **download** + **replace** + **remove-asset** — no row delete (keys are created via the API/frontend and never removed from the CMS). **Remove** (`PUT /media/{media}/remove` → `MediaController::removeMedia`) calls `deleteMedia()` to detach the image/video/file morph while keeping the row (its `url` becomes null until re-filled). `pages/Media/Index.vue` + `components/media/{MediaFilters,MediaTable,MediaEditModal}.vue`. Previews per type, table + grid toggle. **Replace** (`PUT /media/{media}` → `MediaController::update`) swaps the file on an existing item via `saveMedia()` (type re-inferred from the new file's mime, old morph + file cleaned up); the modal uses `ImageUpload` with `accept`/`maxSizeMb` derived from the item's type. Per-type caps reuse `MediaItem::fileRules()` (shared with the API `store`).

**Nuxt** (`starter-nuxt`): `useMedia(group='web', subGroup='general')` mirrors `useLang` — `media(key)` returns the URL, `mediaMeta(key)` the full object, `uploadMedia(key, file)` POSTs multipart FormData through the Nitro proxy (first FormData path in the app). Reads via `useApiFetch`, uploads via `useApi()`. Remote-only (no local mode).

**Seed-on-miss** (like `t(key, default)`): `media(key, '/images/login.png', { subGroup })` returns the backend URL when the key exists; otherwise returns the local `/public` path for immediate render **and** — once, client-side, after the fetch resolves — uploads that public asset to the backend so the key is registered (fetches the path as a Blob → `File` → `uploadMedia`). Guarded by a module-level `seededMedia` Set keyed `group:subGroup:key`. Next fetch returns the stored URL.

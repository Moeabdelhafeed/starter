<?php

namespace App\Http\Controllers\Api\Media;

use App\Helpers\ApiResponse;
use App\Helpers\Trans;
use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    private const ALLOWED_GROUPS = ['app', 'web'];

    /**
     * List Media
     *
     * Return every asset in a group, nested by sub_group.
     *
     *   { group, media: { [subGroup]: { [key]: { type, url, ... } } } }
     *
     * Public endpoint, no authentication required. `group` is optional and
     * defaults to `app`; only `app` or `web` are accepted. Assets whose
     * sub_group is empty are nested under the `general` key.
     *
     * @group Dynamic Storage
     *
     * @groupDescription Keyed media storage (images/videos/files) addressed by group + sub_group + key.
     *
     * @response 200 scenario="Web group with assets" {"success": true, "message": "Media retrieved successfully.", "errors": null, "data": {"group": "web", "media": {"auth": {"login_image": {"type": "image", "url": "http://localhost:8000/storage/dynamic-media/web/auth/login_image.webp", "blurhash": "LKO2?U%2Tw=w]~RBVZRi};RPxuwH"}}, "general": {"terms_pdf": {"type": "file", "url": "http://localhost:8000/storage/dynamic-media/web/general/terms_pdf.pdf", "name": "terms.pdf", "size": 20480}}}}}
     * @response 200 scenario="Group with no assets yet" {"success": true, "message": "Media retrieved successfully.", "errors": null, "data": {"group": "app", "media": []}}
     * @response 422 scenario="Invalid group" {"success": false, "message": "The selected group is invalid.", "errors": {"group": ["The selected group is invalid."]}, "data": null}
     */
    public function index(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
        ]);

        $group = $request->input('group', 'app');

        $media = [];

        MediaItem::where('group', $group)
            ->with(['image', 'video', 'file'])
            ->get()
            ->each(function (MediaItem $item) use (&$media) {
                $subGroup = $item->sub_group !== '' ? $item->sub_group : 'general';
                $media[$subGroup][$item->key] = $item->toApi();
            });

        return ApiResponse::success([
            'group' => $group,
            'media' => $media,
        ], Trans::get('api.media'));
    }

    /**
     * Upload or Replace Media
     *
     * Create or replace the asset at (key, group, sub_group) from a multipart upload.
     * The media type is inferred from the uploaded file's mime.
     *
     * Public endpoint, no authentication required. `group` is optional
     * (defaults to `app`; only `app`/`web` accepted). `sub_group` and `key`
     * are required slugs matching `^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$`.
     * Re-posting the same (key, group, sub_group) replaces the existing
     * asset, even across types (the old file is deleted). The per-type size
     * cap and mime whitelist (config/dynamic-storage.php) are enforced after
     * the type is detected from the file's mime, with errors keyed under `file`.
     *
     * @group Dynamic Storage
     *
     * @response 200 scenario="Image saved" {"success": true, "message": "Media saved successfully.", "errors": null, "data": {"group": "web", "sub_group": "auth", "key": "login_image", "type": "image", "url": "http://localhost:8000/storage/dynamic-media/web/auth/login_image.webp", "blurhash": "LKO2?U%2Tw=w]~RBVZRi};RPxuwH"}}
     * @response 200 scenario="File saved" {"success": true, "message": "Media saved successfully.", "errors": null, "data": {"group": "web", "sub_group": "legal", "key": "terms_pdf", "type": "file", "url": "http://localhost:8000/storage/dynamic-media/web/legal/terms_pdf.pdf", "name": "terms.pdf", "size": 20480}}
     * @response 422 scenario="Missing required fields" {"success": false, "message": "The sub group field is required. (and 2 more errors)", "errors": {"sub_group": ["The sub group field is required."], "key": ["The key field is required."], "file": ["The file field is required."]}, "data": null}
     * @response 422 scenario="Invalid sub_group/key slug format" {"success": false, "message": "The sub group field format is invalid. (and 1 more error)", "errors": {"sub_group": ["The sub group field format is invalid."], "key": ["The key field format is invalid."]}, "data": null}
     * @response 422 scenario="File exceeds per-type size cap" {"success": false, "message": "The file field must not be greater than 2048 kilobytes.", "errors": {"file": ["The file field must not be greater than 2048 kilobytes."]}, "data": null}
     */
    public function store(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
            'sub_group' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/'],
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/'],
            'file' => ['required', 'file'],
        ]);

        $group = $request->input('group', 'app');
        $subGroup = $request->input('sub_group');
        $key = $request->input('key');
        $file = $request->file('file');

        $type = MediaItem::detectType($file);

        // Enforce the per-type cap + mime whitelist. Errors are keyed under `file`.
        Validator::make(['file' => $file], [
            'file' => MediaItem::fileRules($type),
        ])->validate();

        $item = MediaItem::firstOrNew([
            'key' => $key,
            'group' => $group,
            'sub_group' => $subGroup,
        ]);
        $item->saveMedia($file, $type);
        $item->load(['image', 'video', 'file']);

        return ApiResponse::success(array_merge([
            'group' => $group,
            'sub_group' => $subGroup,
            'key' => $key,
        ], $item->toApi()), Trans::get('api.media_saved'));
    }

    /**
     * Delete Media
     *
     * Delete the asset at (key, group, sub_group) — removes the underlying file and the row itself
     * (unlike the admin CMS's "remove", which only detaches the file and keeps the row).
     *
     * @group Dynamic Storage
     *
     * @response 200 scenario="Success" {"success": true, "message": "Media deleted successfully.", "errors": null, "data": null}
     * @response 404 scenario="No media item at that (key, group, sub_group)" {"success": false, "message": "Media not found.", "errors": null, "data": null}
     * @response 422 scenario="Missing required fields" {"success": false, "message": "The sub group field is required. (and 1 more error)", "errors": {"sub_group": ["The sub group field is required."], "key": ["The key field is required."]}, "data": null}
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'group' => ['nullable', 'string', 'in:'.implode(',', self::ALLOWED_GROUPS)],
            'sub_group' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/'],
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/'],
        ]);

        $group = $request->input('group', 'app');
        $subGroup = $request->input('sub_group');
        $key = $request->input('key');

        $item = MediaItem::where('key', $key)
            ->where('group', $group)
            ->where('sub_group', $subGroup)
            ->first();

        if (! $item) {
            return ApiResponse::error(Trans::get('api.media_not_found'), null, 404);
        }

        $item->deleteMedia();
        $item->delete();

        return ApiResponse::success(null, Trans::get('api.media_deleted'));
    }
}

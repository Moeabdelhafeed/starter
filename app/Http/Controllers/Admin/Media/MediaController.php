<?php

namespace App\Http\Controllers\Admin\Media;

use App\Http\Controllers\Controller;
use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MediaController extends Controller
{
    /**
     * Available media groups for the filter dropdown.
     */
    protected array $groups = ['all', 'app', 'web'];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $group = $request->input('group');
        $subGroup = $request->input('sub_group');

        $items = MediaItem::query()
            ->group($group)
            ->subGroup($subGroup)
            ->when($search, function ($query, $search) {
                $query->where('key', 'like', "%{$search}%");
            })
            ->with(['image', 'video', 'file'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (MediaItem $item) => array_merge([
                'id' => $item->id,
                'key' => $item->key,
                'group' => $item->group,
                'sub_group' => $item->sub_group,
            ], $item->toApi()));

        // Distinct sub-groups (app/web only) for the filter dropdown.
        $subGroups = MediaItem::query()
            ->whereIn('group', ['app', 'web'])
            ->where('sub_group', '!=', '')
            ->distinct()
            ->orderBy('sub_group')
            ->pluck('sub_group')
            ->toArray();

        return Inertia::render('Media/Index', [
            'items' => Inertia::scroll($items),
            'groups' => $this->groups,
            'subGroups' => array_merge(['all'], $subGroups),
            'filters' => [
                'search' => $search,
                'group' => $group,
                'sub_group' => $subGroup,
            ],
        ]);
    }

    /**
     * Replace the file on an existing media item. Keys are never deleted from the CMS —
     * an admin can only download the current asset or swap it for a new one. The new
     * file's type is inferred from its mime (a swap may change image ↔ video ↔ file).
     */
    public function update(Request $request, MediaItem $media)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $file = $request->file('file');
        $type = MediaItem::detectType($file);

        Validator::make(['file' => $file], [
            'file' => MediaItem::fileRules($type),
        ])->validate();

        $media->saveMedia($file, $type);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    /**
     * Remove the attached asset (image/video/file) but keep the row/key. The item stays
     * addressable at its (key, group, sub_group); its URL just becomes null until a new
     * file is uploaded (via the CMS replace or the frontend API).
     */
    public function removeMedia(MediaItem $media)
    {
        $media->deleteMedia();

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }
}

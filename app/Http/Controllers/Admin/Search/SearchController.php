<?php

namespace App\Http\Controllers\Admin\Search;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Language;
use App\Models\MediaItem;
use App\Models\NotificationTemplate;
use App\Models\Page;
use App\Models\Role;
use App\Models\TranslationKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Backs the command palette (Cmd/Ctrl+K) — one query across every CMS module.
 *
 * Each module is included only when its feature flag is on AND the signed-in
 * admin holds its permission, so the palette can never surface a row from a
 * screen that admin cannot open. Results deep-link to the module's own list
 * with `search` (so the row is on the first page) and `highlight` (so it
 * glows on arrival) rather than to bespoke detail pages that don't exist.
 */
class SearchController extends Controller
{
    /** Rows returned per module. The palette is a jump list, not a report. */
    private const PER_GROUP = 5;

    /**
     * Activity logs are the one group where many rows can share a term without
     * being distinct records to jump to, so they contribute fewer.
     */
    private const PER_GROUP_LOG = 3;

    /** Longer needles are almost always a paste; cap before it reaches SQL. */
    private const MAX_TERM = 100;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:'.self::MAX_TERM],
        ]);

        $term = trim((string) ($validated['q'] ?? ''));

        if ($term === '') {
            return response()->json(['groups' => []]);
        }

        $groups = collect([
            $this->users($term),
            $this->appUsers($term),
            $this->roles($term),
            $this->pages($term),
            $this->translations($term),
            $this->languages($term),
            $this->media($term),
            $this->notificationTemplates($term),
            $this->appSettings($term),
            $this->activityLogs($term),
        ])->filter()->values();

        return response()->json(['groups' => $groups]);
    }

    /**
     * Whether this admin may see a module at all: the feature has to be on and
     * the permission held. `Gate::before` lets super_admin through every check.
     */
    private function allows(string $permission, bool $featureEnabled = true): bool
    {
        return $featureEnabled && (bool) request()->user()?->can($permission);
    }

    /**
     * Shape one module's hits, or null when it is off-limits or found nothing.
     *
     * @param  Collection<int, array{id: int|string, title: string, subtitle?: string|null}>  $items
     * @return array{key: string, route: string, items: array<int, array<string, mixed>>}|null
     */
    private function group(string $key, string $routeName, Collection $items, string $term): ?array
    {
        if ($items->isEmpty()) {
            return null;
        }

        return [
            'key' => $key,
            'route' => $routeName,
            'items' => $items->map(fn (array $item): array => [
                'id' => $item['id'],
                'title' => $item['title'],
                'subtitle' => $item['subtitle'] ?? null,
                'url' => route($routeName, array_filter([
                    'search' => $term,
                    'highlight' => $item['id'],
                ])),
            ])->values()->all(),
        ];
    }

    /**
     * `LIKE` needs its own wildcards escaped, or a term containing % or _
     * silently matches far more than the admin typed.
     */
    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
    }

    private function users(string $term): ?array
    {
        if (! $this->allows('users')) {
            return null;
        }

        $rows = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('guard_name', 'web'))
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $this->like($term))
                ->orWhere('email', 'like', $this->like($term)))
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'title' => $user->name,
                'subtitle' => $user->email,
            ]);

        return $this->group('users', 'users', $rows, $term);
    }

    private function appUsers(string $term): ?array
    {
        $enabled = config('features.app_users') || config('features.app_guests');

        if (! $this->allows('app_users', $enabled)) {
            return null;
        }

        $rows = User::query()
            ->where(fn (Builder $q) => $q
                ->where('is_guest', true)
                ->orWhereHas('roles', fn (Builder $r) => $r->where('guard_name', 'api')))
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $this->like($term))
                ->orWhere('email', 'like', $this->like($term))
                ->orWhere('phone', 'like', $this->like($term)))
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'title' => $user->name ?: (string) ($user->email ?? $user->phone),
                'subtitle' => $user->email ?? $user->phone,
            ]);

        return $this->group('app_users', 'app_users', $rows, $term);
    }

    private function roles(string $term): ?array
    {
        if (! $this->allows('roles')) {
            return null;
        }

        $rows = Role::query()
            ->where('guard_name', 'web')
            ->where('name', 'like', $this->like($term))
            ->withCount('users')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'title' => $role->name,
                'subtitle' => __('admin.users').': '.$role->users_count,
            ]);

        return $this->group('roles', 'roles', $rows, $term);
    }

    private function pages(string $term): ?array
    {
        if (! $this->allows('pages', (bool) config('features.pages'))) {
            return null;
        }

        $rows = Page::query()
            ->where(fn (Builder $q) => $q
                ->where('slug', 'like', $this->like($term))
                ->orWhereHas('translations', fn (Builder $t) => $t
                    ->where('field', 'name')
                    ->where('value', 'like', $this->like($term))))
            ->with('translations')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Page $page): array => [
                'id' => $page->id,
                'title' => $page->name_api ?: $page->slug,
                'subtitle' => '/p/'.$page->slug,
            ]);

        return $this->group('pages', 'pages', $rows, $term);
    }

    private function translations(string $term): ?array
    {
        if (! $this->allows('translations', (bool) config('features.translations'))) {
            return null;
        }

        $rows = TranslationKey::query()
            ->where(fn (Builder $q) => $q
                ->where('key', 'like', $this->like($term))
                ->orWhereHas('values', fn (Builder $v) => $v->where('value', 'like', $this->like($term))))
            ->limit(self::PER_GROUP)
            ->get(['id', 'key', 'group', 'sub_group'])
            ->map(fn (TranslationKey $key): array => [
                'id' => $key->id,
                'title' => $key->key,
                'subtitle' => trim($key->group.' · '.$key->sub_group, ' ·'),
            ]);

        return $this->group('translations', 'translations', $rows, $term);
    }

    private function languages(string $term): ?array
    {
        if (! $this->allows('translations', (bool) config('features.translations'))) {
            return null;
        }

        $rows = Language::query()
            ->where(fn (Builder $q) => $q
                ->where('code', 'like', $this->like($term))
                ->orWhere('name', 'like', $this->like($term))
                ->orWhere('native_name', 'like', $this->like($term)))
            ->limit(self::PER_GROUP)
            ->get(['id', 'code', 'name', 'native_name'])
            ->map(fn (Language $language): array => [
                'id' => $language->id,
                'title' => $language->name,
                'subtitle' => $language->code.' · '.$language->native_name,
            ]);

        return $this->group('languages', 'languages', $rows, $term);
    }

    private function media(string $term): ?array
    {
        if (! $this->allows('dynamic_storage', (bool) config('features.dynamic_storage'))) {
            return null;
        }

        $rows = MediaItem::query()
            ->where('key', 'like', $this->like($term))
            ->limit(self::PER_GROUP)
            ->get(['id', 'key', 'group', 'sub_group', 'type'])
            ->map(fn (MediaItem $item): array => [
                'id' => $item->id,
                'title' => $item->key,
                'subtitle' => $item->group.' · '.$item->sub_group.' · '.$item->type,
            ]);

        return $this->group('media', 'media', $rows, $term);
    }

    private function notificationTemplates(string $term): ?array
    {
        if (! $this->allows('notification_templates', (bool) config('features.notification_templates'))) {
            return null;
        }

        $rows = NotificationTemplate::query()
            ->where(fn (Builder $q) => $q
                ->where('slug', 'like', $this->like($term))
                ->orWhere('topic', 'like', $this->like($term))
                ->orWhereHas('translations', fn (Builder $t) => $t
                    ->where('field', 'title')
                    ->where('value', 'like', $this->like($term))))
            ->with('translations')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (NotificationTemplate $template): array => [
                'id' => $template->id,
                'title' => $template->title_api ?: $template->slug,
                'subtitle' => $template->topic,
            ]);

        return $this->group('notification_templates', 'notification_templates', $rows, $term);
    }

    private function appSettings(string $term): ?array
    {
        if (! $this->allows('app_settings', (bool) config('features.app_settings'))) {
            return null;
        }

        $rows = AppSetting::query()
            ->where(fn (Builder $q) => $q
                ->where('url', 'like', $this->like($term))
                ->orWhere('type', 'like', $this->like($term))
                ->orWhereHas('translations', fn (Builder $t) => $t
                    ->where('field', 'text')
                    ->where('value', 'like', $this->like($term))))
            ->with('translations')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (AppSetting $setting): array => [
                'id' => $setting->id,
                'title' => $setting->text_api ?: $setting->url,
                'subtitle' => $setting->type,
            ]);

        return $this->group('app_settings', 'app_settings', $rows, $term);
    }

    private function activityLogs(string $term): ?array
    {
        if (! $this->allows('activity_logs', (bool) config('features.activity_logs'))) {
            return null;
        }

        $rows = ActivityLog::query()
            ->where(fn (Builder $q) => $q
                ->where('causer_email', 'like', $this->like($term))
                ->orWhere('action', 'like', $this->like($term))
                ->orWhere('subject_type', 'like', $this->like($term)))
            ->latest()
            ->limit(self::PER_GROUP_LOG)
            ->get(['id', 'action', 'subject_type', 'subject_id', 'causer_email', 'created_at'])
            ->map(fn (ActivityLog $log): array => [
                'id' => $log->id,
                // Without the record and the timestamp every row reads
                // identically, which is exactly how this group became noise.
                'title' => trim($log->action.' · '.class_basename((string) $log->subject_type).($log->subject_id ? ' #'.$log->subject_id : '')),
                'subtitle' => trim(($log->causer_email ?? '').' · '.$log->created_at?->diffForHumans(), ' ·'),
            ]);

        return $this->group('activity_logs', 'activity_logs', $rows, $term);
    }
}

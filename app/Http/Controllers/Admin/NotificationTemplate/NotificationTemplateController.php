<?php

namespace App\Http\Controllers\Admin\NotificationTemplate;

use App\Helpers\FcmTopics;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationTemplate;
use App\Models\Language;
use App\Models\NotificationTemplate;
use App\Services\NotificationModelRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $templates = NotificationTemplate::query()
            ->with('translations')
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('slug', 'like', "%{$search}%")
                        ->orWhere('topic', 'like', "%{$search}%")
                        ->orWhereHas('translations', fn ($tq) => $tq->where('field', 'title')->where('value', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->scrollPaginate(10);

        return Inertia::render('NotificationTemplate/Index', [
            'templates' => Inertia::scroll($templates),
            'filters' => ['search' => $request->input('search')],
            'topics' => FcmTopics::structured(),
            'models' => NotificationModelRegistry::all(),
            'events' => NotificationTemplate::TRIGGER_EVENTS,
            'languages' => Language::active()->get(['id', 'code', 'name', 'native_name', 'direction']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $template = NotificationTemplate::create([
            'slug' => $validated['slug'],
            'topic' => $validated['topic'],
            'trigger_model' => $validated['trigger_model'] ?? null,
            'trigger_event' => $validated['trigger_event'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $template->saveTranslations($validated['translations']);

        return redirect()->back()->with('success', __('admin.created_successfully'))->with('highlight', $template->id);
    }

    public function update(Request $request, NotificationTemplate $notification_template)
    {
        $validated = $this->validatePayload($request, $notification_template->id);

        // A system template's wiring belongs to the code that looks it up: the slug and
        // the trigger stay as seeded. Its copy, topic and on/off switch are the admin's.
        $isSystem = $notification_template->isSystem();

        $notification_template->update([
            'slug' => $isSystem ? $notification_template->slug : $validated['slug'],
            'topic' => $validated['topic'],
            'trigger_model' => $isSystem ? $notification_template->trigger_model : ($validated['trigger_model'] ?? null),
            'trigger_event' => $isSystem ? $notification_template->trigger_event : ($validated['trigger_event'] ?? null),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $notification_template->saveTranslations($validated['translations']);

        return redirect()->back()->with('success', __('admin.updated_successfully'))->with('highlight', $notification_template->id);
    }

    public function destroy(NotificationTemplate $notification_template)
    {
        if ($notification_template->isSystem()) {
            return redirect()->back()->with('error', __('admin.template_is_system'));
        }

        $notification_template->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * On-click manual fire. Dispatches the job synchronously (queue=sync)
     * which calls FCMHelper::sendToTopic, then stamps last_sent_at.
     */
    public function sendNow(NotificationTemplate $notification_template)
    {
        if (! $notification_template->is_active) {
            return redirect()->back()->with('error', __('admin.template_inactive'));
        }

        SendNotificationTemplate::dispatchSync($notification_template->id);

        return redirect()->back()->with('success', __('admin.notification_sent'));
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'exists:notification_templates,id'],
        ]);

        // System templates are skipped rather than failing the whole selection; the
        // model would refuse them silently, and the admin should be told which.
        $templates = NotificationTemplate::whereIn('id', $request->ids)->get();
        $skipped = $templates->filter(fn (NotificationTemplate $t): bool => $t->isSystem());

        // Deleted one by one, not as a mass `->delete()`: the query-builder form fires no
        // model events, so HasTranslations never cleans up and the copy is orphaned.
        foreach ($templates->diff($skipped) as $template) {
            $template->delete();
        }

        if ($skipped->isNotEmpty()) {
            return redirect()->back()->with('error', __('admin.templates_system_skipped', [
                'slugs' => $skipped->pluck('slug')->implode(', '),
            ]));
        }

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = Rule::unique('notification_templates', 'slug');
        if ($ignoreId) {
            $slugRule = $slugRule->ignore($ignoreId);
        }

        return $request->validate([
            'slug' => ['required', 'string', 'regex:/^[a-z0-9_-]+$/', 'max:64', $slugRule],
            'topic' => ['required', 'string', Rule::in(FcmTopics::selectable())],
            'trigger_model' => ['nullable', 'string', Rule::in(array_column(NotificationModelRegistry::all(), 'class'))],
            'trigger_event' => ['nullable', 'string', Rule::in(NotificationTemplate::TRIGGER_EVENTS)],
            'is_active' => ['boolean'],
            'translations' => ['required', 'array'],
            'translations.title' => ['required', 'array'],
            'translations.title.*' => ['nullable', 'string', 'max:255'],
            'translations.body' => ['required', 'array'],
            'translations.body.*' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}

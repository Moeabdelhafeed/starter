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
            ->paginate(10)
            ->withQueryString();

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

        return redirect()->back()->with('success', __('admin.created_successfully'));
    }

    public function update(Request $request, NotificationTemplate $notification_template)
    {
        $validated = $this->validatePayload($request, $notification_template->id);

        $notification_template->update([
            'slug' => $validated['slug'],
            'topic' => $validated['topic'],
            'trigger_model' => $validated['trigger_model'] ?? null,
            'trigger_event' => $validated['trigger_event'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $notification_template->saveTranslations($validated['translations']);

        return redirect()->back()->with('success', __('admin.updated_successfully'));
    }

    public function destroy(NotificationTemplate $notification_template)
    {
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

        NotificationTemplate::whereIn('id', $request->ids)->delete();

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
            'topic' => ['required', 'string', Rule::in(FcmTopics::all())],
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

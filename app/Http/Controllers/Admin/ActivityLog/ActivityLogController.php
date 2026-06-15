<?php

namespace App\Http\Controllers\Admin\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\Exportable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $action = $request->input('action');
        $subjectType = $request->input('subject_type');
        $causerEmail = $request->input('causer');

        $logs = ActivityLog::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('causer_name', 'like', "%{$search}%")
                        ->orWhere('causer_email', 'like', "%{$search}%");
                });
            })
            ->when($action, fn ($q, $action) => $q->where('action', $action))
            ->when($subjectType, fn ($q, $type) => $q->where('subject_type', $type))
            ->when($causerEmail, fn ($q, $email) => $q->where('causer_email', $email))
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('ActivityLog/Index', [
            'logs' => Inertia::scroll($logs),
            'filters' => [
                'search' => $search,
                'action' => $action,
                'subject_type' => $subjectType,
                'causer' => $causerEmail,
            ],
            'actions' => ActivityLog::distinct()->pluck('action'),
            'subjectTypes' => ActivityLog::distinct()->whereNotNull('subject_type')->pluck('subject_type')
                ->map(fn ($t) => ['value' => $t, 'label' => class_basename($t)]),
            'causers' => ActivityLog::distinct()->whereNotNull('causer_email')->pluck('causer_email', 'causer_name')
                ->map(fn ($email, $name) => ['value' => $email, 'label' => $name])->values(),
            'hasExport' => in_array(Exportable::class, class_uses_recursive(ActivityLog::class)),
        ]);
    }

    public function export(Request $request)
    {
        $search = $request->input('search');
        $action = $request->input('action');
        $subjectType = $request->input('subject_type');
        $causerEmail = $request->input('causer');

        return ActivityLog::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('causer_name', 'like', "%{$search}%")
                        ->orWhere('causer_email', 'like', "%{$search}%");
                });
            })
            ->when($action, fn ($q, $action) => $q->where('action', $action))
            ->when($subjectType, fn ($q, $type) => $q->where('subject_type', $type))
            ->when($causerEmail, fn ($q, $email) => $q->where('causer_email', $email))
            ->orderByDesc('created_at')
            ->exportCsv('activity-logs-'.now()->format('Y-m-d-His').'.csv');
    }

    public function destroy($id)
    {
        ActivityLog::findOrFail($id)->delete();

        return back()->with('success', __('admin.deleted_successfully'));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'exists:activity_logs,id'],
        ]);

        ActivityLog::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }
}

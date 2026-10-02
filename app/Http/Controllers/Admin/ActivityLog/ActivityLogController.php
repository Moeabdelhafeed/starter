<?php

namespace App\Http\Controllers\Admin\ActivityLog;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\Exportable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->filteredQuery($request)
            ->scrollPaginate(10);

        return Inertia::render('ActivityLog/Index', [
            'logs' => Inertia::scroll($logs),
            'filters' => [
                'search' => $request->input('search'),
                'action' => $request->input('action'),
                'subject_type' => $request->input('subject_type'),
                'causer' => $request->input('causer'),
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
        return $this->filteredQuery($request)
            ->exportCsv('activity-logs-'.now()->format('Y-m-d-His').'.csv');
    }

    /**
     * The log list the current filters describe. Shared by index() and export()
     * so the CSV always matches what is on screen.
     */
    protected function filteredQuery(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('causer_name', 'like', "%{$search}%")
                        ->orWhere('causer_email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->input('subject_type'), fn ($q, $type) => $q->where('subject_type', $type))
            ->when($request->input('causer'), fn ($q, $email) => $q->where('causer_email', $email))
            ->orderByDesc('created_at');
    }
}

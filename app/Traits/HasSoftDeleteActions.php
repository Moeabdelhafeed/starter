<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Provides soft delete action methods for admin controllers.
 *
 * Usage in controller:
 *   use HasSoftDeleteActions;
 *   protected string $model = Page::class;
 *
 * Then add routes:
 *   Route::post('/{page}/restore', [PageController::class, 'restore'])->name('pages.restore')->withTrashed();
 *   Route::delete('/{page}/force-delete', [PageController::class, 'forceDelete'])->name('pages.force_delete')->withTrashed();
 *   Route::post('/bulk-restore', [PageController::class, 'bulkRestore'])->name('pages.bulk_restore');
 *   Route::post('/bulk-force-delete', [PageController::class, 'bulkForceDelete'])->name('pages.bulk_force_delete');
 */
trait HasSoftDeleteActions
{
    /**
     * Restore a soft-deleted model.
     */
    public function restore(Request $request, $id)
    {
        $model = $this->getModelClass()::withTrashed()->findOrFail($id);

        try {
            $model->restore();
        } catch (ValidationException $e) {
            $msg = $e->validator->errors()->first() ?: __('admin.restore_failed');

            return redirect()->back()->with('error', $msg);
        }

        return redirect()->back()->with('success', __('admin.restored_successfully'));
    }

    /**
     * Permanently delete a model.
     */
    public function forceDelete(Request $request, $id)
    {
        $model = $this->getModelClass()::withTrashed()->findOrFail($id);

        // Call cleanup methods if they exist
        if (method_exists($model, 'deleteImage')) {
            $model->deleteImage();
        }
        if (method_exists($model, 'deleteVideo')) {
            $model->deleteVideo();
        }

        $model->forceDelete();

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * Restore multiple soft-deleted models.
     *
     * Iterates per-row and catches ValidationException so a single blocked
     * row (e.g. BlocksRestoreIfParentTrashed firing) doesn't abort the batch.
     * Reports the count of restored vs skipped.
     */
    public function bulkRestore(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $models = $this->getModelClass()::withTrashed()
            ->whereIn('id', $validated['ids'])
            ->get();

        $restored = 0;
        $skipped = 0;

        foreach ($models as $model) {
            try {
                $model->restore();
                $restored++;
            } catch (ValidationException $e) {
                $skipped++;
            }
        }

        if ($skipped > 0) {
            return redirect()->back()->with(
                'success',
                __('admin.bulk_restore_partial', ['restored' => $restored, 'skipped' => $skipped])
            );
        }

        return redirect()->back()->with('success', __('admin.restored_successfully'));
    }

    /**
     * Permanently delete multiple models.
     */
    public function bulkForceDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $models = $this->getModelClass()::withTrashed()
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($models as $model) {
            if (method_exists($model, 'deleteImage')) {
                $model->deleteImage();
            }
            if (method_exists($model, 'deleteVideo')) {
                $model->deleteVideo();
            }
            $model->forceDelete();
        }

        return redirect()->back()->with('success', __('admin.deleted_successfully'));
    }

    /**
     * Get the model class. Override this or set $model property.
     */
    protected function getModelClass(): string
    {
        if (property_exists($this, 'model')) {
            return $this->model;
        }

        throw new \RuntimeException('Define $model property or override getModelClass() in '.static::class);
    }

    /**
     * Helper: Check if the model uses SoftDeletes.
     */
    protected function modelUsesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->getModelClass()));
    }

    /**
     * Helper: Apply trashed filter to query based on request.
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function applyTrashedFilter($query, Request $request)
    {
        $trashed = $request->input('trashed');

        if (! $this->modelUsesSoftDeletes()) {
            return $query;
        }

        return match ($trashed) {
            'only' => $query->onlyTrashed(),
            'with' => $query->withTrashed(),
            default => $query, // 'without' or null - default behavior
        };
    }

    /**
     * Helper: Get soft delete meta for frontend.
     */
    protected function getSoftDeleteMeta(): array
    {
        return [
            'has_soft_deletes' => $this->modelUsesSoftDeletes(),
        ];
    }
}

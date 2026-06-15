<?php

namespace App\Traits;

use Illuminate\Validation\ValidationException;

/**
 * Blocks soft-delete restore on a child model when one of its declared parent
 * relations is itself trashed. Prevents orphan-ish rows pointing at a trashed
 * parent (FK semantically dangling, eager loads scoped past the parent).
 *
 * Usage:
 *   class Post extends Model {
 *       use SoftDeletes, BlocksRestoreIfParentTrashed;
 *
 *       protected array $blockRestoreIfTrashed = ['user'];
 *   }
 */
trait BlocksRestoreIfParentTrashed
{
    protected static function bootBlocksRestoreIfParentTrashed(): void
    {
        static::restoring(function ($model): void {
            $relations = $model->blockRestoreIfTrashed ?? [];

            foreach ($relations as $relation) {
                $parent = $model->{$relation}()->withTrashed()->first();

                if ($parent && method_exists($parent, 'trashed') && $parent->trashed()) {
                    throw ValidationException::withMessages([
                        $relation => __('admin.cannot_restore_with_trashed_parent', [
                            'relation' => $relation,
                        ]),
                    ]);
                }
            }
        });
    }
}

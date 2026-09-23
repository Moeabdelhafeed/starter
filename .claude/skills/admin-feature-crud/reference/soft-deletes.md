# Soft Deletes (Dynamic)

When a model uses Laravel's `SoftDeletes` trait, the UI adapts automatically.

**Step 1: Add SoftDeletes to model:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model {
    use SoftDeletes, HasImage, LogsActivity;
}
```

**Step 2: Add migration for deleted_at:**
```php
$table->softDeletes(); // adds deleted_at column
```

**Step 3: Add `HasSoftDeleteActions` trait to controller:**
```php
use App\Traits\HasSoftDeleteActions;

class ProductController extends Controller {
    use HasSoftDeleteActions;

    protected string $model = Product::class;

    public function index(Request $request) {
        $products = Product::query()
            ->when($request->input('trashed') === 'only', fn($q) => $q->onlyTrashed())
            ->when($request->input('trashed') === 'with', fn($q) => $q->withTrashed())
            ->paginate(10);

        return Inertia::render('Product/Index', [
            'products' => $products,
            'filters' => ['trashed' => $request->input('trashed')],
            'hasSoftDeletes' => true, // Tell frontend this model supports soft deletes
        ]);
    }
}
```

**Step 4: Add routes (bulk routes before parameterized):**
```php
Route::prefix('products')->middleware('permission:products')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::post('/', [ProductController::class, 'store'])->name('products.store');
    Route::post('/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk_destroy');
    Route::post('/bulk-restore', [ProductController::class, 'bulkRestore'])->name('products.bulk_restore');
    Route::post('/bulk-force-delete', [ProductController::class, 'bulkForceDelete'])->name('products.bulk_force_delete');
    Route::put('/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/{product}/restore', [ProductController::class, 'restore'])->name('products.restore')->withTrashed();
    Route::delete('/{product}/force-delete', [ProductController::class, 'forceDelete'])->name('products.force_delete')->withTrashed();
});
```

**Step 5: Frontend — use TrashedFilter and conditional BulkActions:**
```vue
<script setup>
import TrashedFilter from '@/components/Shared/TrashedFilter.vue';
import BulkActions from '@/components/Shared/BulkActions.vue';
import RestoreModal from '@/components/Shared/RestoreModal.vue';
import BulkRestoreModal from '@/components/Shared/BulkRestoreModal.vue';

const props = defineProps({
    products: Object,
    filters: Object,
    hasSoftDeletes: Boolean,
});

const trashedFilter = ref(props.filters?.trashed || '');
const isViewingTrashed = computed(() => trashedFilter.value === 'only');

// Conditional bulk actions based on view
const bulkActions = computed(() => ({
    delete: !isViewingTrashed.value,
    statusOn: !isViewingTrashed.value,
    statusOff: !isViewingTrashed.value,
    restore: isViewingTrashed.value,
    forceDelete: isViewingTrashed.value,
}));

const applyTrashedFilter = (value) => {
    router.get(route('products.index'), { trashed: value }, { preserveState: true });
};
</script>

<template>
    <!-- Add TrashedFilter to filters section -->
    <TrashedFilter
        v-if="hasSoftDeletes"
        v-model="trashedFilter"
        @update:modelValue="applyTrashedFilter"
    />

    <!-- BulkActions with conditional buttons -->
    <BulkActions
        :selected-count="selectedIds.length"
        :actions="bulkActions"
        @delete="openBulkDeleteModal"
        @restore="openBulkRestoreModal"
        @forceDelete="openBulkForceDeleteModal"
    />

    <!-- In table, show "Deleted" badge for trashed items -->
    <span v-if="product.deleted_at" class="text-xs text-red-500">{{ t('trashed') }}</span>
</template>
```

**Soft delete components available:**
- `TrashedFilter` — dropdown for active/with trashed/trashed only
- `RestoreModal` — single item restore confirmation
- `BulkRestoreModal` — bulk restore confirmation
- `BulkActions` supports `restore` and `forceDelete` actions

**Edit modal — populate form from translations array:**
```vue
import { useTranslations } from '@/composables/useTranslations';
const { translationsToObject } = useTranslations();

watch(() => props.brand, (newBrand) => {
    if (newBrand) {
        const translations = translationsToObject(newBrand.translations, ['name', 'description']);
        form.translations = translations;
    }
}, { immediate: true });
```

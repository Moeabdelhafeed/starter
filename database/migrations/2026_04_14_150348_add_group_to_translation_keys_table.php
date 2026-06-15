<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add group column if it doesn't exist
        if (! Schema::hasColumn('translation_keys', 'group')) {
            Schema::table('translation_keys', function (Blueprint $table) {
                $table->string('group')->default('custom')->after('key');
            });
        }

        // Drop old unique constraint on 'key' if it exists
        $this->dropIndexIfExists('translation_keys', 'translation_keys_key_unique');

        // Add composite unique constraint if it doesn't exist
        $this->addUniqueIfNotExists('translation_keys', ['key', 'group'], 'translation_keys_key_group_unique');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('translation_keys')) {
            return;
        }

        // Drop composite unique constraint if it exists
        $this->dropIndexIfExists('translation_keys', 'translation_keys_key_group_unique');

        // Check if there are duplicate keys across groups
        $duplicates = DB::table('translation_keys')
            ->select('key')
            ->groupBy('key')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            // Delete duplicates, keeping only the first occurrence (lowest id) per key
            $duplicateKeys = DB::table('translation_keys')
                ->select('key')
                ->groupBy('key')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('key');

            foreach ($duplicateKeys as $key) {
                $firstId = DB::table('translation_keys')
                    ->where('key', $key)
                    ->orderBy('id')
                    ->value('id');

                DB::table('translation_keys')
                    ->where('key', $key)
                    ->where('id', '!=', $firstId)
                    ->delete();
            }
        }

        Schema::table('translation_keys', function (Blueprint $table) {
            // Restore original unique constraint on 'key'
            $table->unique('key', 'translation_keys_key_unique');

            $table->dropColumn('group');
        });
    }

    /**
     * Drop an index if it exists.
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        if (count($indexes) > 0) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    /**
     * Add unique constraint if it doesn't exist.
     */
    private function addUniqueIfNotExists(string $table, array $columns, string $indexName): void
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        if (count($indexes) === 0) {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->unique($columns, $indexName);
            });
        }
    }
};

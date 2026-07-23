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
        // Add sub_group column if it doesn't exist.
        // NOT NULL default '' (empty sentinel): api rows have no sub-group,
        // and a nullable column would let MySQL treat every NULL as distinct,
        // allowing duplicate api keys under the composite unique.
        if (! Schema::hasColumn('translation_keys', 'sub_group')) {
            Schema::table('translation_keys', function (Blueprint $table) {
                $table->string('sub_group')->default('')->after('group');
            });
        }

        // Swap the (key, group) unique for a (key, group, sub_group) unique.
        $this->dropIndexIfExists('translation_keys', 'translation_keys_key_group_unique');
        $this->addUniqueIfNotExists('translation_keys', ['key', 'group', 'sub_group'], 'translation_keys_key_group_sub_group_unique');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('translation_keys')) {
            return;
        }

        // Drop the 3-column unique if it exists.
        $this->dropIndexIfExists('translation_keys', 'translation_keys_key_group_sub_group_unique');

        // Collapse any rows that would collide on (key, group) once sub_group is gone,
        // keeping the lowest id per pair, so the 2-column unique can be restored.
        $duplicates = DB::table('translation_keys')
            ->select('key', 'group')
            ->groupBy('key', 'group')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $firstId = DB::table('translation_keys')
                ->where('key', $dup->key)
                ->where('group', $dup->group)
                ->orderBy('id')
                ->value('id');

            DB::table('translation_keys')
                ->where('key', $dup->key)
                ->where('group', $dup->group)
                ->where('id', '!=', $firstId)
                ->delete();
        }

        $this->addUniqueIfNotExists('translation_keys', ['key', 'group'], 'translation_keys_key_group_unique');

        Schema::table('translation_keys', function (Blueprint $table) {
            $table->dropColumn('sub_group');
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'translation_values_key_locale_unique';

    /**
     * One value per (translation_key_id, locale). Nothing enforced this before, so a
     * double-submit in the Translations CMS could leave two rows for the same locale
     * and Trans::get() would pick whichever the query happened to return first.
     *
     * Existing installs may already hold those duplicates, so collapse them before
     * adding the constraint. Pure query-builder — no MySQL-only SQL, because the test
     * suite runs SQLite in memory.
     */
    public function up(): void
    {
        if (! Schema::hasTable('translation_values') || Schema::hasIndex('translation_values', self::INDEX)) {
            return;
        }

        $this->deduplicate();

        Schema::table('translation_values', function (Blueprint $table): void {
            $table->unique(['translation_key_id', 'locale'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('translation_values') || ! Schema::hasIndex('translation_values', self::INDEX)) {
            return;
        }

        Schema::table('translation_values', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX);
        });
    }

    /**
     * Keep the newest row per (translation_key_id, locale) pair — the auto-increment id
     * is the tiebreaker, since two duplicates can share a created_at to the second.
     */
    private function deduplicate(): void
    {
        $duplicates = DB::table('translation_values')
            ->select('translation_key_id', 'locale')
            ->groupBy('translation_key_id', 'locale')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keepId = DB::table('translation_values')
                ->where('translation_key_id', $duplicate->translation_key_id)
                ->where('locale', $duplicate->locale)
                ->orderByDesc('id')
                ->value('id');

            DB::table('translation_values')
                ->where('translation_key_id', $duplicate->translation_key_id)
                ->where('locale', $duplicate->locale)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }
};

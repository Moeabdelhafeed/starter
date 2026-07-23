<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The API `store` endpoint seeds placeholder rows with `value = null` for every
     * active locale other than the request's locale (admin fills them in later via the
     * CMS). That requires `value` to be nullable.
     */
    public function up(): void
    {
        Schema::table('translation_values', function (Blueprint $table) {
            $table->string('value')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill nulls before restoring the NOT NULL constraint.
        DB::table('translation_values')->whereNull('value')->update(['value' => '']);

        Schema::table('translation_values', function (Blueprint $table) {
            $table->string('value')->nullable(false)->change();
        });
    }
};

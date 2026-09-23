<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            // Set by a seeder, never by the CMS. Non-null means application code sends
            // this notification by looking the row up — the copy is the admin's, the
            // wiring is the code's. See NotificationTemplate::forSystemType().
            $table->string('system_type')->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropUnique(['system_type']);
            $table->dropColumn('system_type');
        });
    }
};

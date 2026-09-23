<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A write the assistant proposed and the administrator has not approved yet.
 *
 * Stored server-side rather than held in the browser on purpose: the confirm
 * request names a message id, and the action that runs is the one this row
 * holds. If the payload travelled to the client and back, confirming would
 * mean executing whatever the browser sent — the approval step would be
 * decorative.
 *
 * `performed_at` makes confirming idempotent: a double-click, a retried
 * request or a stale tab cannot run the same write twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->json('pending_action')->nullable()->after('tools_used');
            $table->timestamp('performed_at')->nullable()->after('pending_action');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['pending_action', 'performed_at']);
        });
    }
};

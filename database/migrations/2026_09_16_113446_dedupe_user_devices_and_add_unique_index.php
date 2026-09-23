<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per device per user, enforced by the database.
 *
 * `IdentifyDevice` looked a row up and created one when it found none, which two requests
 * from the same phone run at once both pass — so a device accumulated rows, every one of
 * them carrying the same `fcm_token`. `User::fcmTokens()` collects them all and hands the
 * lot to `sendMulticast()`, so a single notification was delivered once per duplicate row:
 * the customer's tray showed the same push a dozen times.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dedupe();

        Schema::table('user_devices', function (Blueprint $table) {
            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'device_id']);
        });
    }

    /**
     * Keep the newest row per (user_id, device_id) — it holds the current FCM token — and
     * drop the rest. Done id by id rather than with a delete-join so it runs the same on
     * MySQL and SQLite.
     */
    private function dedupe(): void
    {
        $keep = DB::table('user_devices')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('user_id', 'device_id')
            ->pluck('id');

        DB::table('user_devices')->whereNotIn('id', $keep)->delete();
    }
};

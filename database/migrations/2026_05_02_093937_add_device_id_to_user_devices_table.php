<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_devices', 'device_id')) {
            return;
        }

        Schema::table('user_devices', function (Blueprint $table) {
            $table->string('device_id', 64)->nullable()->after('personal_access_token_id')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('user_devices', 'device_id')) {
            return;
        }

        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropColumn('device_id');
        });
    }
};

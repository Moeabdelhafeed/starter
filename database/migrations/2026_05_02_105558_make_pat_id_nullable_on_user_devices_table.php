<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign(['personal_access_token_id']);
            $table->dropUnique(['personal_access_token_id']);
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->change();
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->foreign('personal_access_token_id')
                ->references('id')->on('personal_access_tokens')
                ->cascadeOnDelete();
            $table->unique('personal_access_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropForeign(['personal_access_token_id']);
            $table->dropUnique(['personal_access_token_id']);
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->unsignedBigInteger('personal_access_token_id')->nullable(false)->change();
        });

        Schema::table('user_devices', function (Blueprint $table) {
            $table->foreign('personal_access_token_id')
                ->references('id')->on('personal_access_tokens')
                ->cascadeOnDelete();
            $table->unique('personal_access_token_id');
        });
    }
};

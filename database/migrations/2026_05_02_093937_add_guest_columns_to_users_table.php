<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('is_active')->index();
            $table->string('platform', 10)->nullable()->after('is_guest');
            $table->string('guest_id', 64)->nullable()->after('platform');
            $table->timestamp('last_seen_at')->nullable()->after('guest_id');

            $table->unique('guest_id');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['guest_id']);
            $table->dropIndex(['last_seen_at']);
            $table->dropIndex(['is_guest']);
            $table->dropColumn(['is_guest', 'platform', 'guest_id', 'last_seen_at']);
        });
    }
};

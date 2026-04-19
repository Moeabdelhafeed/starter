<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // google.com, apple.com, etc.
            $table->string('provider_id'); // Firebase UID
            $table->string('email')->nullable(); // Email from provider
            $table->string('name')->nullable(); // Name from provider
            $table->timestamps();

            $table->unique(['provider', 'provider_id']); // Each social account can only be linked once
            $table->unique(['user_id', 'provider']); // One provider per user (can be changed via setting)
        });

        // Remove old columns from users table if they exist
        if (Schema::hasColumn('users', 'provider')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['provider', 'provider_id']);
                $table->dropColumn(['provider', 'provider_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        // Restore old columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('fcm_token');
            $table->string('provider_id')->nullable()->after('provider');
            $table->index(['provider', 'provider_id']);
        });
    }
};

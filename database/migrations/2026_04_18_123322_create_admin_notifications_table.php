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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // app_users, activity_logs, etc.
            $table->string('title_key'); // Translation key for title (e.g., admin.notification_created)
            $table->string('message_key')->nullable(); // Translation key for message
            $table->string('model_key'); // Translation key for model name (e.g., admin.model_user)
            $table->string('action')->nullable(); // created, deleted, updated
            $table->nullableMorphs('notifiable'); // The model that triggered the notification
            $table->json('data')->nullable(); // Additional data including 'name' for message placeholder
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};

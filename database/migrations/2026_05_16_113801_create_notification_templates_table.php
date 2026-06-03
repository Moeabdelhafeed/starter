<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('topic');
            $table->string('trigger_model')->nullable();
            $table->string('trigger_event')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['trigger_model', 'trigger_event', 'is_active'], 'nt_trigger_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};

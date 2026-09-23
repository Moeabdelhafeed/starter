<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved chats for the AI assistant.
 *
 * A conversation belongs to one admin and is only ever reachable through that
 * admin's own relation — these hold questions about the business and whatever
 * the assistant read back, so one admin's history is not another's to browse.
 * Deleting the admin takes their history with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Derived from the first question; nullable until that lands.
            $table->string('title')->nullable();
            $table->timestamps();

            // The sidebar lists one admin's chats, newest first.
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            // Which tools produced the answer, so the transcript stays auditable
            // after the fact — an answer with no tool behind it is one the model
            // made up.
            $table->json('tools_used')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};

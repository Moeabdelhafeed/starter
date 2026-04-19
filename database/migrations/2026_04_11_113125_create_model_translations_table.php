<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('field');
            $table->string('locale', 10);
            $table->text('value');
            $table->timestamps();

            $table->unique(['translatable_id', 'translatable_type', 'field', 'locale'], 'model_translations_unique');
            $table->index(['translatable_type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_translations');
    }
};

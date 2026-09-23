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
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('group');
            // Empty-string sentinel (never NULL) keeps the composite unique valid.
            $table->string('sub_group')->default('');
            $table->string('type'); // image | video | file
            $table->timestamps();

            $table->unique(['key', 'group', 'sub_group'], 'media_items_key_group_sub_group_unique');
            $table->index(['group', 'sub_group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};

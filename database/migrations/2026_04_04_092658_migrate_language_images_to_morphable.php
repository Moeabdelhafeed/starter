<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing language images to the images table
        $languages = DB::table('languages')->whereNotNull('image')->where('image', '!=', '')->get();

        foreach ($languages as $language) {
            DB::table('images')->insert([
                'url' => $language->image,
                'type' => pathinfo($language->image, PATHINFO_EXTENSION),
                'imageable_id' => $language->id,
                'imageable_type' => 'App\\Models\\Language',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->string('image')->nullable()->after('direction');
        });

        $images = DB::table('images')->where('imageable_type', 'App\\Models\\Language')->get();

        foreach ($images as $image) {
            DB::table('languages')->where('id', $image->imageable_id)->update(['image' => $image->url]);
        }

        DB::table('images')->where('imageable_type', 'App\\Models\\Language')->delete();
    }
};

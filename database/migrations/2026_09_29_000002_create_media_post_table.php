<?php

use App\Models\Post;
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
        // Which images each post uses (thumbnail, image fields, images in
        // text), kept up to date when posts are saved. See Post::syncMedia().
        Schema::create('media_post', function (Blueprint $table) {
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();

            $table->primary(['media_id', 'post_id']);
        });

        Post::query()->with('template')->each(fn (Post $post) => $post->syncMedia());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_post');
    }
};

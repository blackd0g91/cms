<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('status');
            $table->unsignedBigInteger('thumbnail_id')->nullable();
            $table->json('data');
            $table->timestamp('created_at');

            $table->index(['post_id', 'created_at']);
        });

        // Start every existing post's history with how it is now.
        DB::table('posts')->orderBy('id')->each(function (object $post) {
            DB::table('post_revisions')->insert([
                'post_id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'thumbnail_id' => $post->thumbnail_id,
                'data' => $post->data,
                'created_at' => $post->updated_at ?? now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};

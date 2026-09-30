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
        // Links at the bottom of the site's sidebar, in order. Each goes to an
        // address or to a post, and is deleted along with its post.
        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->string('url', 2048)->nullable();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('emoji', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A member's own links, as tiles on their page, in their order. Only
        // ever shown to them.
        Schema::create('quick_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->string('url', 2048);
            $table->string('emoji', 32)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_links');
    }
};

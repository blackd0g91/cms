<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A member's own labels, each with a color, for their notes and
        // reminders. Apart from the posts' tags, which are the site's.
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('color', 7);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('label_note', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->primary(['label_id', 'note_id']);
        });

        Schema::create('label_reminder', function (Blueprint $table) {
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->primary(['label_id', 'reminder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_reminder');
        Schema::dropIfExists('label_note');
        Schema::dropIfExists('labels');
    }
};

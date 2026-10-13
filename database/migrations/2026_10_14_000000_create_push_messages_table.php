<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Push notifications an admin sent from the control panel, and how
        // they went, to look back on.
        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            // Null for everyone with notifications on.
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('to_everyone')->default(false);
            $table->string('title', 60);
            $table->string('body', 200);
            $table->string('url', 255);
            $table->unsignedInteger('people')->default(0);
            $table->unsignedInteger('reached')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The apps and sites shown as cards on the home page, in order, each
        // with a logo and a faded background image of its own.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('url', 2048);
            $table->string('description', 160)->nullable();
            $table->foreignId('logo_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('background_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

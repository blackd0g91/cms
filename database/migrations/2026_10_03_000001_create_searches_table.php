<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What visitors searched for, per day. Only counts: nothing about who searched.
        Schema::create('searches', function (Blueprint $table) {
            $table->date('date');
            $table->string('query', 100);
            $table->unsignedInteger('times')->default(0);

            $table->primary(['date', 'query']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('searches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where visitors came from, per day: the site that linked here (like
        // "google.com"), a ?ref= or ?utm_source= name, or "" for none. Only
        // counts: nothing about who came.
        Schema::create('referrers', function (Blueprint $table) {
            $table->date('date');
            $table->string('source', 100);
            $table->unsignedInteger('visits')->default(0);

            $table->primary(['date', 'source']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrers');
    }
};

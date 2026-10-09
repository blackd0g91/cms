<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clicks on the sidebar links and profile icons, per day. Only counts:
        // nothing about who clicked. The target is "link:{id}" or "profile:{site}".
        Schema::create('link_clicks', function (Blueprint $table) {
            $table->date('date');
            $table->string('target', 50);
            $table->unsignedInteger('clicks')->default(0);

            $table->primary(['date', 'target']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_clicks');
    }
};

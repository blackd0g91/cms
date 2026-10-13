<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where a member last asked for the weather, rounded to about a
        // kilometer: {latitude, longitude, name}.
        Schema::table('users', function (Blueprint $table) {
            $table->json('location')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};

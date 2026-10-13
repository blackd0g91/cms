<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The cards a member chose for the top of the home page, in their
        // order (see HomeWidget), or null for the defaults.
        Schema::table('users', function (Blueprint $table) {
            $table->json('home_widgets')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('home_widgets');
        });
    }
};

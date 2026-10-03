<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deleted posts and images go to the trash first (see App\Cms\Trash).
        foreach (['posts', 'media'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'media'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};

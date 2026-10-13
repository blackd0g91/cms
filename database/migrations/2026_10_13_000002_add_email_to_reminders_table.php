<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            // Chosen per reminder, as the site can only send so many emails a day.
            $table->boolean('email')->default(false)->after('repeat');
            // When it was last emailed (or passed over), so each time it is
            // due is emailed once.
            $table->dateTime('emailed_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['email', 'emailed_at']);
        });
    }
};

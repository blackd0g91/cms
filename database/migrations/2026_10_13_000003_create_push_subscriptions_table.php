<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The browsers members turned push notifications on in: where their
        // push service takes messages for them, and the keys to encrypt them.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // To find one by its (long) endpoint.
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestamps();
        });

        // When a reminder was last pushed, as emailed_at is for emails.
        Schema::table('reminders', function (Blueprint $table) {
            $table->dateTime('pushed_at')->nullable()->after('emailed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('pushed_at');
        });

        Schema::dropIfExists('push_subscriptions');
    }
};

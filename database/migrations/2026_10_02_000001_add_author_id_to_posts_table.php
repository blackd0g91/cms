<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Posts stay when their author is removed, without an author.
            $table->foreignId('author_id')->nullable()->after('template_id')->constrained('users')->nullOnDelete();
        });

        // With a single user so far, every post is theirs.
        $users = DB::table('users')->pluck('id');

        if ($users->count() === 1) {
            DB::table('posts')->update(['author_id' => $users->first()]);
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
    }
};

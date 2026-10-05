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
            // When the title or fields last changed after publishing, unlike
            // updated_at, which pinning or retagging change too.
            $table->timestamp('content_updated_at')->nullable()->after('published_at');
        });

        // Posts already edited since they were published, going by their
        // history: the last version whose title or fields differ from the
        // version before it.
        DB::table('posts')->whereNotNull('published_at')->orderBy('id')->each(function (object $post) {
            $previous = null;
            $updatedAt = null;

            $revisions = DB::table('post_revisions')
                ->where('post_id', $post->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['title', 'data', 'created_at']);

            foreach ($revisions as $revision) {
                $content = [$revision->title, json_decode($revision->data, true)];

                if ($previous !== null && $content != $previous && $revision->created_at > $post->published_at) {
                    $updatedAt = $revision->created_at;
                }

                $previous = $content;
            }

            if ($updatedAt !== null) {
                DB::table('posts')->where('id', $post->id)->update(['content_updated_at' => $updatedAt]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('content_updated_at');
        });
    }
};

<?php

namespace App\Cms;

use App\Models\Media;
use App\Models\Post;
use Carbon\CarbonImmutable;

/**
 * Deleted posts and images wait in the trash for DAYS days, where they can
 * be restored, and are then deleted for good.
 */
class Trash
{
    public const int DAYS = 30;

    /**
     * Delete for good whatever has been in the trash for over DAYS days.
     * There is no scheduler, so this runs when the dashboard or the trash
     * is opened.
     */
    public function purgeExpired(): void
    {
        (new Post)->pruneAll();
        (new Media)->pruneAll();
    }

    /**
     * Delete everything in the trash for good.
     */
    public function empty(): void
    {
        Post::onlyTrashed()->lazyById()->each(fn (Post $post) => $post->forceDelete());
        Media::onlyTrashed()->lazyById()->each(fn (Media $media) => $media->forceDelete());
    }

    /**
     * Whole days until something deleted at $deletedAt is deleted for good,
     * counting a part of a day as one.
     */
    public static function daysLeft(CarbonImmutable $deletedAt): int
    {
        return max(1, (int) ceil(now()->diffInDays($deletedAt->addDays(self::DAYS))));
    }
}

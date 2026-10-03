<?php

namespace App\Http\Controllers\Cp;

use App\Cms\Trash;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deleted posts and images, to restore or delete for good.
 */
class TrashController extends Controller
{
    public function index(Trash $trash): Response
    {
        $trash->purgeExpired();

        return Inertia::render('cp/Trash', [
            'days' => Trash::DAYS,
            'posts' => Post::onlyTrashed()
                ->with('template:id,name')
                ->latest('deleted_at')
                ->get()
                ->map(fn (Post $post) => [
                    ...$post->only(['id', 'title', 'deleted_at']),
                    'template' => $post->template->name,
                    'days_left' => Trash::daysLeft($post->deleted_at ?? now()),
                ]),
            'media' => Media::onlyTrashed()
                ->latest('deleted_at')
                ->get()
                ->map(fn (Media $media) => [
                    ...$media->only(['id', 'filename', 'url', 'thumb_url', 'deleted_at']),
                    'days_left' => Trash::daysLeft($media->deleted_at ?? now()),
                ]),
        ]);
    }

    public function restorePost(int $id): RedirectResponse
    {
        Post::onlyTrashed()->findOrFail($id)->restore();

        return back();
    }

    public function destroyPost(int $id): RedirectResponse
    {
        Post::onlyTrashed()->findOrFail($id)->forceDelete();

        return back();
    }

    public function restoreMedia(int $id): RedirectResponse
    {
        Media::onlyTrashed()->findOrFail($id)->restore();

        return back();
    }

    public function destroyMedia(int $id): RedirectResponse
    {
        Media::onlyTrashed()->findOrFail($id)->forceDelete();

        return back();
    }

    public function empty(Trash $trash): RedirectResponse
    {
        $trash->empty();

        return back();
    }
}

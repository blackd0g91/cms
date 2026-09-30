<?php

namespace App\Http\Controllers\Site;

use App\Cms\LayoutRenderer;
use App\Cms\PostViews;
use App\Cms\ResponsiveImages;
use App\Cms\ShareImage;
use App\Cms\TableOfContents;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PostController extends Controller
{
    /**
     * List a template's published posts.
     */
    public function index(Template $template): View
    {
        return view('site.index', [
            'template' => $template,
            'posts' => $template->posts()
                ->with('thumbnail')
                ->where('status', PostStatus::Published)
                ->pinnedFirst()
                ->get()
                ->each->setRelation('template', $template),
        ]);
    }

    /**
     * Show a post through its template's layout. Drafts are visible to
     * logged in users so they can be previewed.
     */
    public function show(Request $request, Template $template, Post $post, LayoutRenderer $renderer, TableOfContents $toc, ResponsiveImages $images, PostViews $views, ShareImage $shareImage): View
    {
        abort_unless($post->isPublished() || $request->user(), 404);

        $post->setRelation('template', $template)->load(['thumbnail', 'tags']);
        $views->record($request, $post);

        ['html' => $html, 'headings' => $headings] = $toc->build($images->apply($renderer->render($post)));

        // Link previews can not show SVGs, so those get the drawn picture too.
        $thumbnail = $post->thumbnail?->isSvg() === false ? $post->thumbnail : null;

        return view('site.post', [
            'post' => $post,
            'content' => new HtmlString($html),
            // A single heading is not worth a table of contents.
            'headings' => count($headings) >= 2 ? $headings : [],
            'preview' => $thumbnail
                ? ['url' => $thumbnail->url, 'width' => $thumbnail->width, 'height' => $thumbnail->height]
                : ['url' => $shareImage->url($post), 'width' => ShareImage::WIDTH, 'height' => ShareImage::HEIGHT],
        ]);
    }

    /**
     * The picture for link previews of a post without a thumbnail.
     */
    public function shareImage(Request $request, Template $template, Post $post, ShareImage $image): BinaryFileResponse
    {
        abort_unless($post->isPublished() || $request->user(), 404);

        $post->setRelation('template', $template);
        $response = response()->file($image->path($post), ['Content-Type' => 'image/png']);

        // The address changes with the picture, so the current one can be kept for good.
        $response->headers->set('Cache-Control', match (true) {
            ! $post->isPublished() => 'private, no-cache',
            $request->query('v') === $image->version($post) => 'public, max-age=31536000, immutable',
            default => 'public, no-cache',
        });

        return $response;
    }
}

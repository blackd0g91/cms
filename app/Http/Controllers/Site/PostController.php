<?php

namespace App\Http\Controllers\Site;

use App\Cms\LayoutRenderer;
use App\Cms\ResponsiveImages;
use App\Cms\TableOfContents;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

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
                ->latest('published_at')
                ->get()
                ->each->setRelation('template', $template),
        ]);
    }

    /**
     * Show a post through its template's layout. Drafts are visible to
     * logged in users so they can be previewed.
     */
    public function show(Request $request, Template $template, Post $post, LayoutRenderer $renderer, TableOfContents $toc, ResponsiveImages $images): View
    {
        abort_unless($post->isPublished() || $request->user(), 404);

        $post->setRelation('template', $template)->load(['thumbnail', 'tags']);

        ['html' => $html, 'headings' => $headings] = $toc->build($images->apply($renderer->render($post)));

        return view('site.post', [
            'post' => $post,
            'content' => new HtmlString($html),
            // A single heading is not worth a table of contents.
            'headings' => count($headings) >= 2 ? $headings : [],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\PostRequest;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Template $template): Response
    {
        return Inertia::render('cp/posts/Index', [
            'template' => $template->only(['id', 'name', 'handle']),
            'posts' => $template->posts()
                ->latest('updated_at')
                ->get(['id', 'template_id', 'title', 'slug', 'status', 'published_at', 'updated_at'])
                ->map(fn (Post $post) => [
                    ...$post->only(['id', 'title', 'slug', 'status', 'published_at', 'updated_at']),
                    'url' => $post->setRelation('template', $template)->url(),
                ]),
        ]);
    }

    public function create(Template $template): Response
    {
        return Inertia::render('cp/posts/Edit', [
            'template' => $template->only(['id', 'name', 'handle', 'fields']),
            'post' => null,
        ]);
    }

    public function store(PostRequest $request, Template $template): RedirectResponse
    {
        $post = $template->posts()->make($request->postAttributes());
        $this->touchPublishedAt($post);
        $post->save();

        return redirect()->route('cp.templates.posts.edit', [$template, $post]);
    }

    public function edit(Template $template, Post $post): Response
    {
        return Inertia::render('cp/posts/Edit', [
            'template' => $template->only(['id', 'name', 'handle', 'fields']),
            'post' => [
                ...$post->only(['id', 'title', 'slug', 'status', 'published_at', 'data']),
                'url' => $post->url(),
            ],
        ]);
    }

    public function update(PostRequest $request, Template $template, Post $post): RedirectResponse
    {
        $post->fill($request->postAttributes());
        $this->touchPublishedAt($post);
        $post->save();

        return redirect()->route('cp.templates.posts.edit', [$template, $post]);
    }

    public function destroy(Template $template, Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('cp.templates.posts.index', $template);
    }

    /**
     * Record when a post was first published.
     */
    private function touchPublishedAt(Post $post): void
    {
        if ($post->isPublished() && $post->published_at === null) {
            $post->published_at = now();
        }
    }
}

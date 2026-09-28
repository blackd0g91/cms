<?php

namespace App\Http\Controllers\Cp;

use App\Enums\FieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\PostRequest;
use App\Models\Media;
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
            'media' => [],
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
            'media' => $this->selectedMedia($template, $post),
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
     * The images selected in a post's image fields, keyed by id.
     *
     * @return array<int, Media>
     */
    private function selectedMedia(Template $template, Post $post): array
    {
        $ids = collect($template->fieldTypes())
            ->filter(fn (FieldType $type) => $type === FieldType::Image)
            ->keys()
            ->map(fn (string $handle) => $post->data[$handle] ?? null)
            ->filter();

        return Media::query()->whereKey($ids)->get()->keyBy('id')->all();
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

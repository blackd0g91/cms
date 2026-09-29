<?php

namespace App\Http\Controllers\Cp;

use App\Enums\FieldType;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\PostRequest;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Tag;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    /**
     * Every post, newest edits first, optionally filtered by template and status.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'template' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
        ]);

        $posts = Post::query()
            ->with('template:id,name,handle')
            ->when($filters['template'] ?? null, fn ($query, $id) => $query->where('template_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(30, ['id', 'template_id', 'title', 'slug', 'status', 'published_at', 'updated_at'])
            ->withQueryString()
            ->through(fn (Post $post) => [
                ...$post->only(['id', 'title', 'slug', 'status', 'published_at', 'updated_at']),
                'template' => $post->template->only(['id', 'name']),
                'url' => $post->url(),
            ]);

        return Inertia::render('cp/posts/Index', [
            'posts' => $posts,
            'templates' => Template::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'template' => isset($filters['template']) ? (int) $filters['template'] : null,
                'status' => $filters['status'] ?? null,
            ],
        ]);
    }

    /**
     * Pick the template for a new post. With a single template, skip the choice.
     */
    public function choose(): Response|RedirectResponse
    {
        $templates = Template::query()->orderBy('name')->get(['id', 'name', 'handle', 'description']);

        if ($templates->count() === 1) {
            return redirect()->route('cp.templates.posts.create', $templates->first());
        }

        return Inertia::render('cp/posts/Choose', [
            'templates' => $templates,
        ]);
    }

    public function create(Template $template): Response
    {
        return Inertia::render('cp/posts/Edit', [
            'template' => $template->only(['id', 'name', 'handle', 'fields']),
            'post' => null,
            'media' => [],
            'revisions' => [],
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(PostRequest $request, Template $template): RedirectResponse
    {
        $post = $template->posts()->make($request->postAttributes());
        $this->touchPublishedAt($post);
        $post->save();
        $post->syncTags($request->tagNames());
        $post->recordRevision($request->user());

        return redirect()->route('cp.templates.posts.edit', [$template, $post]);
    }

    public function edit(Template $template, Post $post): Response
    {
        return Inertia::render('cp/posts/Edit', [
            'template' => $template->only(['id', 'name', 'handle', 'fields']),
            'post' => [
                ...$post->only(['id', 'title', 'slug', 'status', 'published_at', 'updated_at', 'thumbnail_id', 'data']),
                'tags' => $post->tags->pluck('name'),
                'url' => $post->url(),
            ],
            'media' => $this->selectedMedia($template, $post),
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
            'revisions' => $post->revisions()
                ->with('user:id,name')
                ->get(['id', 'post_id', 'user_id', 'title', 'status', 'created_at'])
                ->map(fn (PostRevision $revision) => [
                    'id' => $revision->id,
                    'title' => $revision->title,
                    'status' => $revision->status,
                    'created_at' => $revision->created_at,
                    'user' => $revision->user?->name,
                ]),
        ]);
    }

    public function update(PostRequest $request, Template $template, Post $post): RedirectResponse
    {
        $post->fill($request->postAttributes());
        $this->touchPublishedAt($post);
        $post->save();
        $post->syncTags($request->tagNames());
        $post->recordRevision($request->user());

        return redirect()->route('cp.templates.posts.edit', [$template, $post]);
    }

    /**
     * One saved version in full, with the images it uses, for the history view.
     */
    public function revision(Template $template, Post $post, PostRevision $revision): JsonResponse
    {
        abort_unless($revision->post_id === $post->id, 404);

        $imageIds = collect($template->fieldTypes())
            ->filter(fn (FieldType $type) => $type === FieldType::Image)
            ->keys()
            ->map(fn (string $handle) => $revision->data[$handle] ?? null)
            ->push($revision->thumbnail_id)
            ->filter();

        return response()->json([
            'revision' => $revision->only(['id', 'title', 'slug', 'status', 'thumbnail_id', 'data', 'created_at']),
            'media' => Media::query()->whereKey($imageIds)->get()->keyBy('id'),
        ]);
    }

    /**
     * Copy a post into a new draft and open it.
     */
    public function duplicate(Template $template, Post $post): RedirectResponse
    {
        $copy = $post->replicate(['published_at', 'search_index']);
        $copy->title = "{$post->title} (copy)";
        $copy->slug = $this->uniqueSlug($template, "{$post->slug}-copy");
        $copy->status = PostStatus::Draft;
        $copy->setRelation('template', $template);
        $copy->save();
        $copy->syncTags($post->tags->map(fn (Tag $tag) => $tag->name)->all());
        $copy->recordRevision(request()->user());

        return redirect()->route('cp.templates.posts.edit', [$template, $copy]);
    }

    public function destroy(Template $template, Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('cp.posts.index');
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
            ->push($post->thumbnail_id)
            ->filter();

        return Media::query()->whereKey($ids)->get()->keyBy('id')->all();
    }

    /**
     * The slug, or the slug with the first free number appended.
     */
    private function uniqueSlug(Template $template, string $slug): string
    {
        $candidate = $slug;

        for ($i = 2; $template->posts()->where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
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

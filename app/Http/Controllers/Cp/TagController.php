<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('cp/tags/Index', [
            'tags' => Tag::query()->withCount('posts')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Rename a tag. Its URL follows the new name.
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ])['name']));
        $slug = Str::slug($name);

        if ($slug === '' || Tag::query()->where('slug', $slug)->whereKeyNot($tag->id)->exists()) {
            throw ValidationException::withMessages([
                "name.{$tag->id}" => $slug === '' ? 'The name needs letters or numbers.' : "There is already a tag called \"{$name}\".",
            ]);
        }

        $tag->update(['name' => $name, 'slug' => $slug]);

        // Posts are found by their tag names in search.
        $tag->posts()->with('template')->each(
            fn (Post $post) => $post->forceFill(['search_index' => $post->buildSearchIndex()])->saveQuietly(),
        );

        return back();
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $posts = $tag->posts()->with('template')->get();
        $tag->delete();

        $posts->each(fn (Post $post) => $post->forceFill(['search_index' => $post->buildSearchIndex()])->saveQuietly());

        return back();
    }
}

<?php

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim($request->string('q')->limit(200, '')->toString());

        $posts = $query === '' || Post::searchTerms($query) === []
            ? null
            : Post::query()
                ->with(['template', 'thumbnail'])
                ->where('status', PostStatus::Published)
                ->search($query)
                ->latest('published_at')
                ->paginate(20)
                ->withQueryString();

        return view('site.search', [
            'query' => $query,
            'posts' => $posts,
            'excerpt' => fn (Post $post) => $this->excerpt($post, $query),
        ]);
    }

    /**
     * A snippet of the post around the first matching word, with matches marked.
     */
    private function excerpt(Post $post, string $query): HtmlString
    {
        $text = $post->plainText();
        $words = preg_split('/\s+/', $query) ?: [];

        $excerpt = collect($words)
            ->map(fn (string $word) => Str::excerpt($text, $word, ['radius' => 90]))
            ->first(fn (?string $excerpt) => filled($excerpt))
            ?? Str::limit($text, 180);

        $html = e($excerpt);

        foreach (array_filter($words, fn (string $word) => mb_strlen($word) > 1) as $word) {
            $html = (string) preg_replace('/('.preg_quote(e($word), '/').')/iu', '<mark>$1</mark>', $html);
        }

        return new HtmlString($html);
    }
}

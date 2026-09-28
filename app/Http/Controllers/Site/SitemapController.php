<?php

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Every public page: home, template listings and published posts.
     */
    public function sitemap(): Response
    {
        return response()
            ->view('site.xml.sitemap', [
                'templates' => Template::query()->orderBy('name')->get(),
                'posts' => Post::query()
                    ->with('template')
                    ->where('status', PostStatus::Published)
                    ->latest('updated_at')
                    ->get(),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Keep crawlers out of the control panel and point them at the sitemap.
     */
    public function robots(): Response
    {
        return response(implode("\n", [
            'User-agent: *',
            'Disallow: /cp',
            'Disallow: /search',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ]))->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}

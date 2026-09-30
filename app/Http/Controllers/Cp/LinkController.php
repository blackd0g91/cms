<?php

namespace App\Http\Controllers\Cp;

use App\Cms\Settings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\LinksRequest;
use App\Models\Link;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The links at the bottom of the site's sidebar, edited as one list.
 */
class LinkController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('cp/Links', [
            'heading' => $settings->get('links_heading'),
            'links' => Link::query()->orderBy('position')->get(['id', 'label', 'url', 'post_id', 'emoji']),
            // Every post, to pick from, by template.
            'templates' => Template::query()
                ->with(['posts' => fn ($query) => $query->orderBy('title')->select(['id', 'template_id', 'title', 'status'])])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Save the list as sent: links keep their id, new ones are added, and
     * the ones left out are deleted.
     */
    public function update(LinksRequest $request, Settings $settings): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $kept = [];

            foreach ($request->links() as $position => $attributes) {
                $link = Link::query()->findOrNew($attributes['id']);
                $link->fill([...Arr::except($attributes, 'id'), 'position' => $position])->save();
                $kept[] = $link->id;
            }

            Link::query()->whereKeyNot($kept)->delete();
        });

        // Kept as an empty string when cleared, which means no heading.
        $settings->update(['links_heading' => (string) $request->validated('heading')]);

        return back();
    }
}

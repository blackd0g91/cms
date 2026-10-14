<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\ProjectsRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The apps and sites shown as cards on the home page, edited as one list,
 * like the links.
 */
class ProjectController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('cp/Projects', [
            'projects' => Project::query()
                ->with(['logo', 'background'])
                ->orderBy('position')
                ->get(['id', 'name', 'url', 'description', 'logo_id', 'background_id']),
        ]);
    }

    /**
     * Save the list as sent: projects keep their id, new ones are added,
     * and the ones left out are deleted (their images stay in the media
     * library).
     */
    public function update(ProjectsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $kept = [];

            foreach ($request->projects() as $position => $attributes) {
                $project = Project::query()->findOrNew($attributes['id']);
                $project->fill([...Arr::except($attributes, 'id'), 'position' => $position])->save();
                $kept[] = $project->id;
            }

            Project::query()->whereKeyNot($kept)->delete();
        });

        return back();
    }
}

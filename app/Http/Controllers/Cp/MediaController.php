<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    /**
     * Allowed image types. SVG is left out since it can carry scripts.
     */
    private const array RULES = ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,avif', 'max:10240'];

    public function index(): Response
    {
        return Inertia::render('cp/media/Index', [
            'media' => $this->all(),
        ]);
    }

    /**
     * The media library as JSON, for the image picker.
     */
    public function library(): JsonResponse
    {
        return response()->json(['media' => $this->all()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['file' => self::RULES]);

        $media = Media::upload($request->file('file'));

        return $request->wantsJson()
            ? response()->json(['media' => $media], 201)
            : back();
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $media->update($request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
        ]));

        return back();
    }

    public function destroy(Media $media): RedirectResponse
    {
        $media->deleteWithFile();

        return back();
    }

    /**
     * @return Collection<int, Media>
     */
    private function all(): Collection
    {
        return Media::query()->latest()->get();
    }
}

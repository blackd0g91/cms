<?php

namespace App\Http\Controllers\Cp;

use App\Cms\MediaUsage;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    /**
     * Allowed image types. SVGs are sanitized on upload (see Media::upload()).
     */
    private const array RULES = ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,avif,svg', 'max:10240'];

    public function index(MediaUsage $usage): Response
    {
        $media = $this->all();

        return Inertia::render('cp/media/Index', [
            'media' => $media,
            'usages' => $usage->forMedia($media),
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

    /**
     * Images that are still in use are only deleted when the request
     * confirms it with "force".
     */
    public function destroy(Request $request, Media $media, MediaUsage $usage): RedirectResponse
    {
        $usages = $usage->for($media);

        if ($usages !== [] && ! $request->boolean('force')) {
            throw ValidationException::withMessages([
                'media' => 'This image is still used in: '.implode(', ', array_column($usages, 'label')).'.',
            ]);
        }

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

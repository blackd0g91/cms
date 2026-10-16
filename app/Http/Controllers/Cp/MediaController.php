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

    /**
     * Change the alt text or the name. Only the name shown in the control
     * panel changes: the file stays where it is, so its URL keeps working.
     */
    public function update(Request $request, Media $media): RedirectResponse
    {
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'filename' => ['sometimes', 'required', 'string', 'max:200', 'not_regex:/[\x00-\x1F\x7F\/\\\\]/'],
        ]);

        if (isset($data['filename'])) {
            $data['filename'] = $this->keepExtension($media, trim($data['filename']));
        }

        $media->update($data);

        return back();
    }

    /**
     * Move an image to the trash. Images that are still in use are only
     * moved when the request confirms it with "force".
     */
    public function destroy(Request $request, Media $media, MediaUsage $usage): RedirectResponse
    {
        $usages = $usage->for($media);

        if ($usages !== [] && ! $request->boolean('force')) {
            throw ValidationException::withMessages([
                'media' => 'This image is still used in: '.implode(', ', array_column($usages, 'label')).'.',
            ]);
        }

        $media->delete();

        return back();
    }

    /**
     * The new name with the file's own extension: renaming "photo.png" to
     * "soup" or "soup.png" gives "soup.png". Another extension would not
     * change what the file is, so it would only mislabel it.
     */
    private function keepExtension(Media $media, string $name): string
    {
        $extension = pathinfo($media->filename, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $name;
        }

        if (strcasecmp(pathinfo($name, PATHINFO_EXTENSION), $extension) === 0) {
            $name = trim(pathinfo($name, PATHINFO_FILENAME));
        }

        if ($name === '') {
            throw ValidationException::withMessages(['filename' => 'The name can not be empty.']);
        }

        return "{$name}.{$extension}";
    }

    /**
     * @return Collection<int, Media>
     */
    private function all(): Collection
    {
        return Media::query()->latest()->get();
    }
}

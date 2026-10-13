<?php

namespace App\Http\Controllers\Site;

use App\Cms\AppIcon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppIconController extends Controller
{
    /**
     * The site's icon as an app (see AppIcon), at one of its sizes.
     */
    public function __invoke(Request $request, AppIcon $icon, int $size): BinaryFileResponse
    {
        abort_unless(in_array($size, AppIcon::SIZES, true), 404);

        $response = response()->file($icon->path($size), ['Content-Type' => 'image/png']);

        // The address changes with the picture, so the current one can be kept for good.
        $response->headers->set('Cache-Control', $request->query('v') === $icon->version()
            ? 'public, max-age=31536000, immutable'
            : 'public, no-cache');

        return $response;
    }
}

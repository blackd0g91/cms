<?php

namespace App\Http\Controllers\Site;

use App\Cms\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The site as an app, for adding it to a phone's home screen: on iPhones,
 * that is what lets it get push notifications.
 */
class ManifestController extends Controller
{
    public function __invoke(Settings $settings): JsonResponse
    {
        return response()->json([
            'name' => $settings->siteName(),
            'short_name' => $settings->siteName(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f5efe3',
            'theme_color' => '#f5efe3',
            'icons' => [
                ['src' => '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
                ['src' => '/apple-touch-icon.png', 'sizes' => '166x166', 'type' => 'image/png'],
            ],
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}

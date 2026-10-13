<?php

namespace App\Http\Controllers\Site;

use App\Cms\AppIcon;
use App\Cms\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The site as an app, for adding it to a phone's home screen: on iPhones,
 * that is what lets it get push notifications.
 */
class ManifestController extends Controller
{
    public function __invoke(Settings $settings, AppIcon $icon): JsonResponse
    {
        return response()->json([
            'name' => $settings->siteName(),
            'short_name' => $settings->siteName(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f5efe3',
            'theme_color' => '#f5efe3',
            // The logo set on the Settings page, as a square (see AppIcon).
            'icons' => array_map(fn (int $size) => [
                'src' => $icon->url($size),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
            ], AppIcon::SIZES),
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}

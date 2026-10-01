<?php

namespace App\Cms;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes as SVG, dark on white so phones read them in either theme.
 */
class QrCode
{
    public static function svg(string $data, int $size = 176): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, margin: 1), new SvgImageBackEnd)))
            ->writeString($data);

        // Inline in the page: no XML declaration, and screen readers skip it.
        return str_replace('<svg ', '<svg aria-hidden="true" ', (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg));
    }
}

<?php

namespace App\Cms\Widgets;

use App\Cms\QrCode;

/**
 * {{ qr:https://example.com }} or {{ qr:any text | A caption }}: a QR code
 * to scan with a phone, for links, Wi-Fi passwords, phone numbers...
 */
class QrWidget implements Widget
{
    public function name(): string
    {
        return 'qr';
    }

    public function render(string $value): ?string
    {
        [$data, $caption] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if ($data === '' || mb_strlen($data) > 1000) {
            return null;
        }

        return '<span class="widget widget-qr">'
            .QrCode::svg($data)
            .'<span class="widget-qr-caption">'.e($caption !== '' ? $caption : $data).'</span>'
            .'</span>';
    }
}

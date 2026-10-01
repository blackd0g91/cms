<?php

namespace App\Cms;

/**
 * Emoji by shortcode (like "pizza" for 🍕), from the same data as the control
 * panel's emoji picker (resources/js/data/emoji.json, built with
 * `npm run emoji:data`).
 */
class Emoji
{
    /**
     * @var array<string, string>|null
     */
    private static ?array $shortcodes = null;

    public static function fromShortcode(string $shortcode): ?string
    {
        return self::shortcodes()[strtolower($shortcode)] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function shortcodes(): array
    {
        if (self::$shortcodes !== null) {
            return self::$shortcodes;
        }

        $data = json_decode((string) @file_get_contents(resource_path('js/data/emoji.json')), true);
        $shortcodes = [];

        foreach (is_array($data) ? $data['emoji'] ?? [] : [] as $emoji) {
            foreach ($emoji['s'] ?? [] as $shortcode) {
                $shortcodes[$shortcode] = $emoji['e'];
            }
        }

        return self::$shortcodes = $shortcodes;
    }
}

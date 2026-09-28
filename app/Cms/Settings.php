<?php

namespace App\Cms;

use App\Models\Setting;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;

/**
 * Site-wide settings edited in the control panel, cached until they change.
 */
#[Singleton]
class Settings
{
    private const string CACHE_KEY = 'settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * Every setting with its default value.
     *
     * @return array{site_name: string, tagline: string|null, home_intro: string|null, footer_text: string|null}
     */
    public function defaults(): array
    {
        return [
            'site_name' => (string) config('app.name'),
            'tagline' => null,
            'home_intro' => null,
            'footer_text' => null,
        ];
    }

    /**
     * @return array{site_name: string, tagline: string|null, home_intro: string|null, footer_text: string|null}
     */
    public function all(): array
    {
        $this->values ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );

        $values = $this->defaults();

        foreach ($values as $key => $default) {
            $values[$key] = $this->values[$key] ?? $default;
        }

        return $values;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function siteName(): string
    {
        return $this->all()['site_name'];
    }

    /**
     * The footer text with {year} replaced, or a default copyright line.
     */
    public function footer(): string
    {
        $text = $this->all()['footer_text'] ?? '© {year} {site_name}';

        return strtr($text, [
            '{year}' => (string) now()->year,
            '{site_name}' => $this->siteName(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, $this->defaults()) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->values = null;
    }
}

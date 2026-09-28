<?php

namespace App\Cms;

use App\Models\Media;
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
     * @return array{site_name: string, tagline: string|null, home_intro: string|null, footer_text: string|null, logo_id: int|null, favicon_id: int|null}
     */
    public function defaults(): array
    {
        return [
            'site_name' => (string) config('app.name'),
            'tagline' => null,
            'home_intro' => null,
            'footer_text' => null,
            'logo_id' => null,
            'favicon_id' => null,
        ];
    }

    /**
     * @return array{site_name: string, tagline: string|null, home_intro: string|null, footer_text: string|null, logo_id: int|null, favicon_id: int|null}
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
     * The logo shown beside the site name, if one is set and still exists.
     */
    public function logo(): ?Media
    {
        return $this->media('logo_id');
    }

    /**
     * The icon for browser tabs and bookmarks, if one is set and still exists.
     */
    public function favicon(): ?Media
    {
        return $this->media('favicon_id');
    }

    private function media(string $key): ?Media
    {
        $id = $this->get($key);

        return is_int($id) ? Media::query()->find($id) : null;
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

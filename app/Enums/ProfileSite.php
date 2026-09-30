<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The sites a profile can be linked to, shown as icons in the site's header
 * when filled in on the Settings page, in this order.
 *
 * Icons from Tabler Icons (https://tabler.io/icons), MIT License,
 * Copyright (c) 2020-2026 Paweł Kuna.
 */
enum ProfileSite: string
{
    case GitHub = 'github';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case Bluesky = 'bluesky';
    case Mastodon = 'mastodon';
    case Instagram = 'instagram';
    case YouTube = 'youtube';
    case WhatsApp = 'whatsapp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X',
            self::Bluesky => 'Bluesky',
            self::Mastodon => 'Mastodon',
            self::Instagram => 'Instagram',
            self::YouTube => 'YouTube',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
        };
    }

    /**
     * What to type, for the Settings page.
     */
    public function placeholder(): string
    {
        return match ($this) {
            self::GitHub => 'Username, or https://github.com/you',
            self::LinkedIn => 'Username, or https://www.linkedin.com/in/you',
            self::X => '@username, or https://x.com/you',
            self::Bluesky => 'you.bsky.social',
            self::Mastodon => '@you@mastodon.social',
            self::Instagram => '@username, or https://www.instagram.com/you',
            self::YouTube => '@handle, or https://www.youtube.com/@you',
            self::WhatsApp => 'Phone number, like +55 11 91234 5678',
            self::Email => 'you@example.com',
        };
    }

    /**
     * The profile's address from what was typed: usernames and handles
     * become the full address, and addresses get https:// when it is
     * missing. Email stays an address.
     */
    public function normalize(string $value): string
    {
        $value = trim($value);

        return match (true) {
            $value === '' => '',
            $this === self::Email => Str::after($value, 'mailto:'),
            str_contains($value, '/') => preg_match('#^https?://#i', $value) ? $value : "https://{$value}",
            $this === self::WhatsApp => 'https://wa.me/'.preg_replace('/\D/', '', $value),
            default => $this->profileUrl(ltrim($value, '@')) ?? $value,
        };
    }

    /**
     * Whether a normalized value is a profile on this site: an address on
     * its domain with a path, a Mastodon profile on any server, or an email
     * address.
     */
    public function accepts(string $value): bool
    {
        if ($this === self::Email) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
        }

        $url = parse_url($value) ?: [];
        $host = (string) preg_replace('/^(www|m)\./', '', Str::lower($url['host'] ?? ''));
        $path = trim($url['path'] ?? '', '/');

        if (! in_array($url['scheme'] ?? null, ['http', 'https'], true) || $host === '' || $path === '') {
            return false;
        }

        return match ($this) {
            self::GitHub => $host === 'github.com',
            self::LinkedIn => $host === 'linkedin.com',
            self::X => in_array($host, ['x.com', 'twitter.com'], true),
            self::Bluesky => $host === 'bsky.app',
            self::Mastodon => str_starts_with($path, '@'),
            self::Instagram => $host === 'instagram.com',
            self::YouTube => $host === 'youtube.com',
            self::WhatsApp => in_array($host, ['wa.me', 'api.whatsapp.com'], true),
        };
    }

    /**
     * What to say when a value is not a profile on this site.
     */
    public function invalid(): string
    {
        return match ($this) {
            self::Email => 'That doesn’t look like an email address.',
            self::WhatsApp => 'Use your phone number with its country code, like +55 11 91234 5678.',
            self::Mastodon => 'Use your full handle, like @you@mastodon.social, or the address of your profile.',
            default => "That doesn’t look like a {$this->label()} profile. Use your username, or the address of your profile.",
        };
    }

    /**
     * Where the icon links to.
     */
    public function href(string $value): string
    {
        return $this === self::Email ? "mailto:{$value}" : $value;
    }

    /**
     * The icon's shapes, for an <svg> with a 24 by 24 viewBox, drawn with
     * the current color's stroke.
     */
    public function icon(): string
    {
        return match ($this) {
            self::GitHub => '<path d="M9 19c-4.3 1.4 -4.3 -2.5 -6 -3m12 5v-3.5c0 -1 .1 -1.4 -.5 -2c2.8 -.3 5.5 -1.4 5.5 -6a4.6 4.6 0 0 0 -1.3 -3.2a4.2 4.2 0 0 0 -.1 -3.2s-1.1 -.3 -3.5 1.3a12.3 12.3 0 0 0 -6.2 0c-2.4 -1.6 -3.5 -1.3 -3.5 -1.3a4.2 4.2 0 0 0 -.1 3.2a4.6 4.6 0 0 0 -1.3 3.2c0 4.6 2.7 5.7 5.5 6c-.6 .6 -.6 1.2 -.5 2v3.5"/>',
            self::LinkedIn => '<path d="M8 11v5"/><path d="M8 8v.01"/><path d="M12 16v-5"/><path d="M16 16v-3a2 2 0 1 0 -4 0"/><path d="M3 7a4 4 0 0 1 4 -4h10a4 4 0 0 1 4 4v10a4 4 0 0 1 -4 4h-10a4 4 0 0 1 -4 -4l0 -10"/>',
            self::X => '<path d="M4 4l11.733 16h4.267l-11.733 -16l-4.267 0"/><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"/>',
            self::Bluesky => '<path d="M6.335 5.144c-1.654 -1.199 -4.335 -2.127 -4.335 .826c0 .59 .35 4.953 .556 5.661c.713 2.463 3.13 2.75 5.444 2.369c-4.045 .665 -4.889 3.208 -2.667 5.41c1.03 1.018 1.913 1.59 2.667 1.59c2 0 3.134 -2.769 3.5 -3.5c.333 -.667 .5 -1.167 .5 -1.5c0 .333 .167 .833 .5 1.5c.366 .731 1.5 3.5 3.5 3.5c.754 0 1.637 -.571 2.667 -1.59c2.222 -2.203 1.378 -4.746 -2.667 -5.41c2.314 .38 4.73 .094 5.444 -2.369c.206 -.708 .556 -5.072 .556 -5.661c0 -2.953 -2.68 -2.025 -4.335 -.826c-2.293 1.662 -4.76 5.048 -5.665 6.856c-.905 -1.808 -3.372 -5.194 -5.665 -6.856"/>',
            self::Mastodon => '<path d="M18.648 15.254c-1.816 1.763 -6.648 1.626 -6.648 1.626a18.262 18.262 0 0 1 -3.288 -.256c1.127 1.985 4.12 2.81 8.982 2.475c-1.945 2.013 -13.598 5.257 -13.668 -7.636l-.026 -1.154c0 -3.036 .023 -4.115 1.352 -5.633c1.671 -1.91 6.648 -1.666 6.648 -1.666s4.977 -.243 6.648 1.667c1.329 1.518 1.352 2.597 1.352 5.633s-.456 4.074 -1.352 4.944"/><path d="M12 11.204v-2.926c0 -1.258 -.895 -2.278 -2 -2.278s-2 1.02 -2 2.278v4.722m4 -4.722c0 -1.258 .895 -2.278 2 -2.278s2 1.02 2 2.278v4.722"/>',
            self::Instagram => '<path d="M4 8a4 4 0 0 1 4 -4h8a4 4 0 0 1 4 4v8a4 4 0 0 1 -4 4h-8a4 4 0 0 1 -4 -4l0 -8"/><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/><path d="M16.5 7.5v.01"/>',
            self::YouTube => '<path d="M2 8a4 4 0 0 1 4 -4h12a4 4 0 0 1 4 4v8a4 4 0 0 1 -4 4h-12a4 4 0 0 1 -4 -4v-8"/><path d="M10 9l5 3l-5 3l0 -6"/>',
            self::WhatsApp => '<path d="M3 21l1.65 -3.8a9 9 0 1 1 3.4 2.9l-5.05 .9"/><path d="M9 10a.5 .5 0 0 0 1 0v-1a.5 .5 0 0 0 -1 0v1a5 5 0 0 0 5 5h1a.5 .5 0 0 0 0 -1h-1a.5 .5 0 0 0 0 1"/>',
            self::Email => '<path d="M3 7a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-10"/><path d="M3 7l9 6l9 -6"/>',
        };
    }

    /**
     * A profile's address from its username or handle, where the site has
     * one. Anything else is left for accepts() to refuse.
     */
    private function profileUrl(string $handle): ?string
    {
        if ($this !== self::Mastodon && preg_match('/^[\w.-]+$/', $handle) !== 1) {
            return null;
        }

        return match ($this) {
            self::GitHub => "https://github.com/{$handle}",
            self::LinkedIn => "https://www.linkedin.com/in/{$handle}",
            self::X => "https://x.com/{$handle}",
            self::Bluesky => "https://bsky.app/profile/{$handle}",
            self::Mastodon => preg_match('/^([^@\s]+)@([^@\s]+\.[^@\s]+)$/', $handle, $match) ? "https://{$match[2]}/@{$match[1]}" : null,
            self::Instagram => "https://www.instagram.com/{$handle}",
            self::YouTube => "https://www.youtube.com/@{$handle}",
            self::WhatsApp, self::Email => null,
        };
    }
}

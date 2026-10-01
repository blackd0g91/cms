<?php

namespace App\Cms\Widgets;

/**
 * {{ youtube:https://www.youtube.com/watch?v=… }} (or a youtu.be link, or
 * just the video id), with {{ youtube:… | A caption }}. Nothing is loaded
 * from YouTube until the video is played: the page shows a placeholder, and
 * clicking it (resources/js/site.ts) loads the player from
 * youtube-nocookie.com. Without the script it links to the video.
 */
class YouTubeWidget implements Widget
{
    public function name(): string
    {
        return 'youtube';
    }

    public function render(string $value): ?string
    {
        [$address, $caption] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');
        $video = self::parse($address);

        if ($video === null) {
            return null;
        }

        ['id' => $id, 'start' => $start] = $video;
        $watch = "https://www.youtube.com/watch?v={$id}".($start ? "&t={$start}s" : '');
        $title = $caption !== '' ? $caption : 'YouTube video';

        return '<span class="widget widget-youtube" data-youtube="'.$id.'" data-start="'.$start.'">'
            .'<a class="widget-youtube-play" href="'.e($watch).'" target="_blank" rel="noopener" aria-label="Play '.e($title).'">'
            .'<svg class="widget-youtube-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.14v13.72a1 1 0 0 0 1.52 .85l11.07 -6.86a1 1 0 0 0 0 -1.7l-11.07 -6.86a1 1 0 0 0 -1.52 .85z"/></svg>'
            .'<span class="widget-youtube-title">'.e($title).'</span>'
            .'<span class="widget-youtube-note">Plays from YouTube when clicked</span>'
            .'</a>'
            .'</span>';
    }

    /**
     * The video id and start (in seconds) from a link or a bare id.
     *
     * @return array{id: string, start: int}|null
     */
    public static function parse(string $address): ?array
    {
        $id = null;

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $address)) {
            $id = $address;
        } elseif (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $address, $match)) {
            $id = $match[1];
        }

        if ($id === null) {
            return null;
        }

        $start = 0;
        $query = (string) parse_url($address, PHP_URL_QUERY);
        parse_str($query, $parameters);
        $time = $parameters['t'] ?? $parameters['start'] ?? null;

        if (is_string($time) && preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', $time, $parts)) {
            $start = (int) ($parts[1] ?? 0) * 3600 + (int) ($parts[2] ?? 0) * 60 + (int) ($parts[3] ?? 0);
        }

        return ['id' => $id, 'start' => $start];
    }
}

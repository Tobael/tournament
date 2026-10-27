<?php

namespace App\Services;

class TvMusic
{
    public static function embed(string $value): ?string
    {
        $url = parse_url($value);
        if (! is_array($url) || ! in_array($url['scheme'] ?? '', ['http', 'https'], true)
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])) {
            return null;
        }
        $host = strtolower($url['host'] ?? '');
        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be', 'www.youtu.be'], true)) {
            return null;
        }
        $parts = array_values(array_filter(explode('/', $url['path'] ?? '')));
        parse_str($url['query'] ?? '', $query);
        $video = str_ends_with($host, 'youtu.be') ? ($parts[0] ?? null)
            : (in_array($parts[0] ?? '', ['embed', 'shorts', 'live'], true) ? ($parts[1] ?? null) : ($query['v'] ?? null));
        $playlist = $query['list'] ?? null;
        if (($video !== null && (! is_string($video) || ! preg_match('/^[a-zA-Z0-9_-]{11}$/D', $video)))
            || ($playlist !== null && (! is_string($playlist) || ! preg_match('/^[a-zA-Z0-9_-]{10,100}$/D', $playlist)))
            || (! $video && ! $playlist)) {
            return null;
        }

        return 'https://www.youtube.com/embed/'.($video ?: 'videoseries').'?'.http_build_query([
            ...($playlist ? ['list' => $playlist] : []),
            'playsinline' => 1,
        ]);
    }
}

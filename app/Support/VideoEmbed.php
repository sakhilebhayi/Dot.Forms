<?php

namespace App\Support;

/**
 * The only video embeds a form may carry (R-31 §5): YouTube and Vimeo, parsed
 * server-side into a provider + id so the document never echoes a creator-supplied
 * URL into an iframe. Anything else is refused at save with a plain reason.
 */
class VideoEmbed
{
    /** @return array{provider: string, id: string}|null */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            $id = (string) ($query['v'] ?? '');

            if ($id === '' && preg_match('#^/(embed|shorts|live)/([A-Za-z0-9_-]{6,20})#', $path, $m)) {
                $id = $m[2];
            }

            return preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? ['provider' => 'youtube', 'id' => $id] : null;
        }

        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{6,20})$#', $path, $m)) {
            return ['provider' => 'youtube', 'id' => $m[1]];
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true) && preg_match('#/(\d{6,12})(?:/|$)#', $path, $m)) {
            return ['provider' => 'vimeo', 'id' => $m[1]];
        }

        return null;
    }

    /** The src the document's iframe uses; ids only ever come from parse(). */
    public static function src(array $video): ?string
    {
        return match ($video['provider'] ?? null) {
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.$video['id'],
            'vimeo' => 'https://player.vimeo.com/video/'.$video['id'],
            default => null,
        };
    }
}

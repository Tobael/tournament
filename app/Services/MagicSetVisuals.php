<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MagicSetVisuals
{
    public function artwork(string $code): array
    {
        $key = 'set-artwork.v1.'.$code;
        if (Cache::has('set-artwork.fresh.'.$code) || Cache::has('set-artwork.retry.'.$code)) {
            return Cache::get($key, []);
        }
        try {
            $cards = Http::acceptJson()->withUserAgent('Spielrunde/1.0 (Magic set artwork)')
                ->connectTimeout(2)->timeout(5)->get('https://api.scryfall.com/cards/search', [
                    'q' => 'set:'.$code.' game:paper', 'order' => 'edhrec', 'unique' => 'art',
                ])->throw()->json('data', []);
            foreach ($cards as $card) {
                if (in_array($card['image_status'] ?? '', ['missing', 'placeholder'])) {
                    continue;
                }
                $face = isset($card['image_uris']['art_crop']) ? $card : ($card['card_faces'][0] ?? []);
                $image = $face['image_uris']['art_crop'] ?? '';
                $source = $card['scryfall_uri'] ?? '';
                if ($this->isHost($image, 'cards.scryfall.io') && $this->isHost($source, 'scryfall.com')) {
                    $artwork = ['image' => $image, 'artist' => $face['artist'] ?? $card['artist'] ?? 'Unbekannt',
                        'card' => $card['name'], 'source' => $source];
                    Cache::forever($key, $artwork);
                    Cache::put('set-artwork.fresh.'.$code, true, now()->addDay());

                    return $artwork;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
        // Retry shortly when an announced set has no art yet or Scryfall is offline.
        Cache::put('set-artwork.retry.'.$code, true, now()->addMinutes(5));

        return Cache::get($key, []);
    }

    public function symbol(string $code): ?string
    {
        if (Cache::has('set-symbol.retry.'.$code)) {
            return null;
        }
        $key = 'set-symbol.v1.'.$code;
        if ($svg = Cache::get($key)) {
            return $svg;
        }
        try {
            $svg = Http::withUserAgent('Spielrunde/1.0 (Magic set symbols)')->connectTimeout(2)->timeout(5)
                ->get('https://svgs.scryfall.io/sets/'.$code.'.svg')->throw()->body();
            if (! str_contains($svg, '<svg') || strlen($svg) > 200000) {
                throw new \RuntimeException('Invalid set symbol');
            }
            Cache::put($key, $svg, now()->addMonth());

            return $svg;
        } catch (Throwable $exception) {
            report($exception);
            Cache::put('set-symbol.retry.'.$code, true, now()->addMinutes(5));

            return null;
        }
    }

    private function isHost(string $url, string $host): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https' && parse_url($url, PHP_URL_HOST) === $host;
    }
}

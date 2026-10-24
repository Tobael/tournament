<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MagicSetCatalog
{
    public function sets(): array
    {
        if (Cache::has('magic-sets.fresh') || Cache::has('magic-sets.retry')) {
            return Cache::get('magic-sets.catalog', []);
        }

        try {
            $response = Http::acceptJson()->withUserAgent('Spielrunde/1.0 (sealed set selection)')
                ->connectTimeout(2)->timeout(5)->get('https://api.scryfall.com/sets')->throw();
            $sets = collect($response->json('data', []))
                ->filter(fn ($set) => ! ($set['digital'] ?? true)
                    && in_array($set['set_type'] ?? '', ['core', 'expansion', 'masters', 'draft_innovation', 'funny'])
                    && isset($set['code'], $set['name'], $set['released_at']))
                ->sortByDesc('released_at')->map(fn ($set) => [
                    'code' => $set['code'], 'name' => $set['name'], 'released_at' => $set['released_at'],
                ])->values()->all();
            if ($sets === []) {
                throw new \RuntimeException('Empty set catalog');
            }
            Cache::forever('magic-sets.catalog', $sets);
            Cache::put('magic-sets.fresh', true, now()->addDay());

            return $sets;
        } catch (Throwable $exception) {
            report($exception);
            Cache::put('magic-sets.retry', true, now()->addMinutes(5));

            return Cache::get('magic-sets.catalog', []);
        }
    }
}

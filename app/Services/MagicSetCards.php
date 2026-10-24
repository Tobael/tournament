<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MagicSetCards
{
    public function context(string $code): ?array
    {
        $sets = Cache::remember('card-set-context.v1', now()->addDay(), fn () => $this->get('/sets')->json('data', []));
        $main = collect($sets)->firstWhere('code', $code);
        if (! $main || ($main['digital'] ?? true)) {
            return null;
        }
        $related = collect($sets)->filter(fn ($set) => ($set['parent_set_code'] ?? '') === $code
            && ! ($set['digital'] ?? true) && in_array($set['set_type'], ['masterpiece', 'expansion', 'eternal', 'draft_innovation']));

        return ['name' => $main['name'], 'released_at' => $main['released_at'] ?? null,
            'sets' => collect([$main])->merge($related)->map(fn ($set) => ['code' => $set['code'], 'name' => $set['name']])->values()->all()];
    }

    public function topPulls(string $code): array
    {
        $key = 'top-pulls.v1.'.$code;
        if (Cache::has($key.'.fresh') || Cache::has($key.'.retry')) {
            return Cache::get($key, ['normal' => [], 'foil' => [], 'sets' => [], 'updated_at' => null, 'unavailable' => true]);
        }
        try {
            $context = $this->context($code);
            abort_unless($context, 404);
            $terms = array_map(fn ($set) => 'set:'.$set['code'], $context['sets']);
            // Special Guests uses one set code across releases; scope it to this release.
            if ($context['released_at']) {
                $terms[] = '(set:spg date:'.$context['released_at'].')';
            }
            $cards = collect();
            $complete = false;
            for ($page = 1; $page <= 12; $page++) {
                $response = $this->get('/cards/search', ['q' => '('.implode(' or ', $terms).') game:paper', 'unique' => 'prints', 'page' => $page]);
                $data = $response->json();
                foreach ($data['data'] ?? [] as $card) {
                    $normalized = $this->normalize($card);
                    if ($normalized) {
                        $cards->push($normalized);
                    }
                }
                if (! ($data['has_more'] ?? false)) {
                    $complete = true;
                    break;
                }
            }
            if (! $complete) {
                throw new \RuntimeException('Incomplete price catalog');
            }
            $sets = $context['sets'];
            if ($cards->contains('set', 'spg')) {
                $sets[] = ['code' => 'spg', 'name' => 'Special Guests'];
            }
            $result = ['normal' => $cards->filter(fn ($card) => $card['eur'] !== null)->sortByDesc('eur')->take(8)->values()->all(),
                'foil' => $cards->filter(fn ($card) => $card['eur_foil'] !== null)->sortByDesc('eur_foil')->take(8)->values()->all(),
                'sets' => $sets, 'updated_at' => now()->toIso8601String(), 'unavailable' => false];
            Cache::forever($key, $result);
            Cache::put($key.'.fresh', true, now()->addHours(6));

            return $result;
        } catch (Throwable $exception) {
            report($exception);
            Cache::put($key.'.retry', true, now()->addMinutes(5));

            return Cache::get($key, ['normal' => [], 'foil' => [], 'sets' => [], 'updated_at' => null, 'unavailable' => true]);
        }
    }

    public function lookup(string $code, string $subset, string $number): ?array
    {
        $number = preg_replace('/^0+(?=[0-9])/', '', $number);
        $context = $this->context($code);
        abort_unless($context, 404);
        abort_unless(in_array($subset, array_column($context['sets'], 'code'), true) || $subset === 'spg', 422);
        $key = 'card-lookup.v1.'.$code.'.'.$subset.'.'.hash('sha256', $number);
        if (Cache::has($key)) {
            return Cache::get($key) ?: null;
        }
        try {
            $card = $this->get('/cards/'.$subset.'/'.rawurlencode($number))->json();
            // Never show a guest from a different set's release just because its number exists.
            if ($subset === 'spg' && ($card['released_at'] ?? null) !== $context['released_at']) {
                Cache::put($key, [], now()->addMinutes(5));

                return null;
            }
            $result = $this->normalize($card);
            if ($result) {
                $result['priced_at'] = now()->toIso8601String();
            }
            Cache::put($key, $result ?? [], now()->addHours(6));

            return $result;
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }
            Cache::put($key, [], now()->addMinutes(5));

            return null;
        }
    }

    private function normalize(array $card): ?array
    {
        $image = $card['image_uris']['normal'] ?? $card['card_faces'][0]['image_uris']['normal'] ?? '';
        $source = $card['scryfall_uri'] ?? '';
        if (parse_url($image, PHP_URL_HOST) !== 'cards.scryfall.io' || parse_url($image, PHP_URL_SCHEME) !== 'https'
            || parse_url($source, PHP_URL_HOST) !== 'scryfall.com' || parse_url($source, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        return ['name' => $card['name'], 'number' => $card['collector_number'], 'set' => $card['set'],
            'set_name' => $card['set_name'], 'image' => $image, 'source' => $source,
            'eur' => $this->price($card['prices']['eur'] ?? null), 'eur_foil' => $this->price($card['prices']['eur_foil'] ?? null)];
    }

    private function price(mixed $price): ?float
    {
        return is_numeric($price) && (float) $price > 0 ? (float) $price : null;
    }

    private function get(string $path, array $query = [])
    {
        // Keep consecutive requests below Scryfall's recommended request rate.
        usleep(110000);

        return Http::acceptJson()->withUserAgent('Spielrunde/1.0 (sealed card values)')
            ->connectTimeout(2)->timeout(8)->get('https://api.scryfall.com'.$path, $query)->throw();
    }
}

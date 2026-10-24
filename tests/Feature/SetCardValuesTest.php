<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function pricedCard(string $set, string $number, ?string $eur, ?string $foil, string $date = '2024-09-27'): array
{
    return ['name' => strtoupper($set).' Card '.$number, 'collector_number' => $number, 'set' => $set, 'set_name' => strtoupper($set),
        'released_at' => $date, 'image_uris' => ['normal' => 'https://cards.scryfall.io/normal/'.$set.$number.'.jpg'],
        'scryfall_uri' => 'https://scryfall.com/card/'.$set.'/'.$number, 'prices' => ['eur' => $eur, 'eur_foil' => $foil]];
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    Cache::put('card-set-context.v1', [
        ['code' => 'dsk', 'name' => 'Duskmourn', 'released_at' => '2024-09-27', 'digital' => false],
        ['code' => 'wot', 'name' => 'Bonus Sheet', 'parent_set_code' => 'dsk', 'set_type' => 'masterpiece', 'digital' => false],
        ['code' => 'dsc', 'name' => 'Commander Decks', 'parent_set_code' => 'dsk', 'set_type' => 'commander', 'digital' => false],
    ], 86400);
});

test('top pulls include matching guests and bonus sheets and sort normal and foil prices separately', function () {
    Http::fake(['api.scryfall.com/cards/search*' => function ($request) {
        expect($request['q'])->toContain('set:dsk', 'set:wot', '(set:spg date:2024-09-27)')->not->toContain('set:dsc');

        return Http::response(['data' => [
            pricedCard('dsk', '1', '2.50', '90.00'), pricedCard('spg', '66', '25.00', '30.00'),
            pricedCard('wot', '3', '10.00', null), pricedCard('dsk', '4', null, null),
        ], 'has_more' => false]);
    }]);
    $this->getJson(route('set.top-pulls', 'dsk'))->assertOk()->assertJsonPath('normal.0.set', 'spg')
        ->assertJsonPath('normal.0.eur', 25)->assertJsonPath('foil.0.number', '1')->assertJsonCount(3, 'normal')
        ->assertJsonPath('sets.2.code', 'spg');
    $this->getJson(route('set.top-pulls', 'dsk'))->assertOk();
    Http::assertSentCount(1);
});

test('top pulls inspect every page so foil-only prices on later pages are not missed', function () {
    Http::fake(['api.scryfall.com/cards/search*' => fn ($request) => Http::response($request['page'] === 1
        ? ['data' => [pricedCard('dsk', '1', '10', '15')], 'has_more' => true]
        : ['data' => [pricedCard('dsk', '2', null, '200')], 'has_more' => false])]);
    $this->getJson(route('set.top-pulls', 'dsk'))->assertOk()->assertJsonPath('foil.0.eur_foil', 200)->assertJsonPath('foil.0.number', '2');
    Http::assertSentCount(2);
});

test('collector number lookup normalizes leading zeros and caches actual price timestamps', function () {
    Http::fake(['api.scryfall.com/cards/dsk/119' => Http::response(pricedCard('dsk', '119', '12.50', null))]);
    $response = $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'number' => '0119']))->assertOk()
        ->assertJsonPath('card.number', '119')->assertJsonPath('card.eur', 12.5)->assertJsonPath('card.eur_foil', null);
    $timestamp = $response->json('updated_at');
    $this->travel(1)->hours();
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'number' => '119']))->assertOk()->assertJsonPath('updated_at', $timestamp);
    Http::assertSentCount(1);
});

test('guest lookup rejects a guest from a different release and unknown subsets', function () {
    Http::fake(['api.scryfall.com/cards/spg/66' => Http::response(pricedCard('spg', '66', '20', '30', '2024-02-09'))]);
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'subset' => 'spg', 'number' => '66']))->assertNotFound();
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'subset' => 'dsc', 'number' => '1']))->assertStatus(422);
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'number' => '../secrets']))->assertUnprocessable();
    Http::assertSentCount(1);
});

test('unpriced cards stay usable and missing cards and outages have different messages', function () {
    Http::fake([
        'api.scryfall.com/cards/dsk/1' => Http::response(pricedCard('dsk', '1', null, null)),
        'api.scryfall.com/cards/dsk/2' => Http::response([], 404),
        'api.scryfall.com/cards/dsk/3' => Http::response([], 503),
    ]);
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'number' => '1']))->assertOk()->assertJsonPath('card.eur', null);
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'number' => '2']))->assertNotFound()->assertJsonPath('message', 'Diese Kartennummer wurde im gewählten Set nicht gefunden.');
    $this->getJson(route('set.card-lookup', ['code' => 'dsk', 'number' => '3']))->assertStatus(503);
});

test('price outages retain the last successful top pulls with their original timestamp', function () {
    Http::fake(['api.scryfall.com/cards/search*' => Http::sequence()->push(['data' => [pricedCard('dsk', '1', '10', '20')]])->push([], 503)]);
    $timestamp = $this->getJson(route('set.top-pulls', 'dsk'))->assertOk()->json('updated_at');
    $this->travel(7)->hours();
    $this->getJson(route('set.top-pulls', 'dsk'))->assertOk()->assertJsonPath('normal.0.eur', 10)->assertJsonPath('updated_at', $timestamp);
});

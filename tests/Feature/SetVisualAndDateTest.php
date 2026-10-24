<?php

use App\Enums\Status;
use App\Livewire\Tournaments;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

test('set artwork is cached with artist attribution and survives provider outages', function () {
    $art = ['image_uris' => ['art_crop' => 'https://cards.scryfall.io/art_crop/test.jpg'],
        'artist' => 'Test Artist', 'name' => 'Test Card', 'scryfall_uri' => 'https://scryfall.com/card/dsk/1/test'];
    Http::fake(['api.scryfall.com/cards/search*' => Http::response(['data' => [$art]])]);
    $this->getJson(route('set.artwork', 'dsk'))->assertOk()->assertJsonPath('artist', 'Test Artist')->assertJsonPath('image', $art['image_uris']['art_crop']);
    $this->getJson(route('set.artwork', 'dsk'))->assertOk();
    Http::assertSentCount(1);
    $this->travel(25)->hours();
    Http::fake(['api.scryfall.com/cards/search*' => Http::response([], 503)]);
    $this->getJson(route('set.artwork', 'dsk'))->assertOk()->assertJsonPath('artist', 'Test Artist');
});

test('double-faced artwork is supported and untrusted image hosts are ignored', function () {
    Http::fake(['api.scryfall.com/cards/search*' => Http::response(['data' => [
        ['name' => 'Unsafe', 'image_uris' => ['art_crop' => 'https://example.com/image.jpg'], 'scryfall_uri' => 'https://scryfall.com/card/1'],
        ['name' => 'Two Faces', 'scryfall_uri' => 'https://scryfall.com/card/2', 'card_faces' => [
            ['artist' => 'Face Artist', 'image_uris' => ['art_crop' => 'https://cards.scryfall.io/art_crop/face.jpg']],
        ]],
    ]])]);
    $this->getJson(route('set.artwork', 'dsk'))->assertOk()->assertJsonPath('artist', 'Face Artist')->assertJsonPath('card', 'Two Faces');
});

test('unavailable artwork retries after five minutes and symbols are cached locally', function () {
    Http::fake([
        'api.scryfall.com/cards/search*' => Http::response([], 404),
        'svgs.scryfall.io/sets/dsk.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    ]);
    $this->getJson(route('set.artwork', 'dsk'))->assertOk()->assertExactJson([]);
    $this->getJson(route('set.artwork', 'dsk'))->assertOk();
    Http::assertSentCount(1);
    $this->travel(6)->minutes();
    $this->getJson(route('set.artwork', 'dsk'))->assertOk();
    Http::assertSentCount(2);
    $this->get(route('set.symbol', 'dsk'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(route('set.symbol', 'dsk'))->assertOk();
    Http::assertSentCount(3);
    $this->get('/set-visuals/invalid!code/artwork')->assertNotFound();
});

test('set visuals are rendered without blocking on external API calls', function () {
    $event = Tournament::create(['name' => 'Visual Sealed', 'format' => 'sealed', 'status' => Status::OPEN,
        'group_id' => Group::create(['name' => 'Visual Friends'])->id, 'set_code' => 'dsk', 'set_name' => 'Duskmourn', 'scheduled_on' => '2026-11-07']);
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('data-set-visual', false)->assertSee('Duskmourn')->assertSee('07.11.2026')->assertSee('datetime="2026-11-07"', false);
    $this->actingAs(User::factory()->admin()->create())->get(route('tournaments'))->assertOk()->assertSee('data-set-visual', false)->assertSee('07.11.2026');
    Http::assertNothingSent();
});

test('event dates can be saved, validated, edited, sorted and cleared', function () {
    $this->actingAs(User::factory()->admin()->create());
    Cache::put('magic-sets.fresh', true, 60);
    Livewire::test(Tournaments::class)->set('name', 'Later')->set('scheduledDate', '2026-02-30')->call('createTournament')->assertHasErrors('scheduledDate')
        ->set('scheduledDate', '2026-11-07')->call('createTournament')->assertHasNoErrors()->assertSet('scheduledDate', '');
    $event = Tournament::first();
    expect($event->scheduled_on->format('Y-m-d'))->toBe('2026-11-07');
    Livewire::test(Tournaments::class)->set('name', 'Earlier')->set('scheduledDate', '2026-10-30')->call('createTournament')->assertHasNoErrors();
    Livewire::test(Tournaments::class)->call('sort', 'scheduled_on')->assertSeeInOrder(['Earlier', 'Later']);
    Livewire::test(Tournaments::class)->call('openEditModal', $event->id)->assertSet('scheduledDate', '2026-11-07')
        ->set('scheduledDate', '2026-11-14')->call('editTournament')->assertHasNoErrors();
    expect($event->refresh()->scheduled_on->format('Y-m-d'))->toBe('2026-11-14');
    Livewire::test(Tournaments::class)->call('openEditModal', $event->id)->set('scheduledDate', '')->call('editTournament')->assertHasNoErrors();
    expect($event->refresh()->scheduled_on)->toBeNull();
});

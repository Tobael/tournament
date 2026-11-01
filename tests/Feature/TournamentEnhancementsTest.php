<?php

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Livewire\Tournaments;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use App\Services\MagicSetCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function timedEvent(): array
{
    $event = Tournament::create(['name' => 'Sealed Night', 'group_id' => Group::firstOrCreate(['name' => 'Friends'])->id, 'status' => Status::IN_PROGRESS]);
    $players = collect(['Alex', 'Sam'])->map(fn ($name) => $event->users()->create(['user_id' => User::factory()->create(['name' => $name])->id]));
    $round = $event->rounds()->create(['round' => 1]);
    $match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id]);

    return [$event, $match];
}

test('match timers start only inside the counter and preserve their original deadline', function () {
    [$event, $match] = timedEvent();
    $url = route('play.life.update', [$event->invite_token, $match->id]);
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('Lebenszähler öffnen')->assertDontSee('Timer starten');
    $this->postJson($url, ['action' => 'timer', 'minutes' => 50])->assertForbidden();
    $this->actingAs($match->playerA->user);
    $this->freezeTime();
    $this->postJson($url, ['action' => 'timer', 'minutes' => 50])->assertOk();
    $deadline = $match->refresh()->timer_ends_at;
    expect($deadline->timestamp)->toBe(now()->addMinutes(50)->timestamp);
    $this->travel(10)->minutes();
    $this->postJson($url, ['action' => 'timer', 'minutes' => 90])->assertOk();
    expect($match->refresh()->timer_ends_at->equalTo($deadline))->toBeTrue();
    $this->travel(41)->minutes();
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('Zeit abgelaufen')->assertDontSee('Timer starten');
    expect($match->refresh()->result)->toBeNull();
});

test('counter timers reject other events, past rounds, byes, invalid durations and closed events', function () {
    [$event, $match] = timedEvent();
    [$other, $foreignMatch] = timedEvent();
    $this->actingAs($match->playerA->user);
    $command = ['action' => 'timer', 'minutes' => 50];
    $url = fn ($table) => route('play.life.update', [$event->invite_token, $table->id]);
    $this->postJson($url($foreignMatch), $command)->assertNotFound();
    $this->postJson($url($match), [...$command, 'minutes' => -1])->assertUnprocessable();
    $bye = $event->currentRound()->matches()->create(['player_a_id' => $match->player_a_id]);
    $this->postJson($url($bye), $command)->assertConflict();
    $event->rounds()->create(['round' => 2]);
    $this->postJson($url($match), $command)->assertConflict();
    $event->update(['status' => Status::CLOSED]);
    $this->postJson($url($match), $command)->assertConflict();
});

test('a completed match cannot start a timer and closing celebrates all tied winners', function () {
    [$event, $match] = timedEvent();
    $this->actingAs($match->playerA->user);
    $match->update(['result' => RoundMatchResult::DRAW]);
    $this->postJson(route('play.life.update', [$event->invite_token, $match->id]), ['action' => 'timer', 'minutes' => 50])->assertConflict();
    expect($match->refresh()->timer_ends_at)->toBeNull();
    $this->get(route('play', $event->invite_token))->assertDontSee('Siegerehrung');
    $event->update(['status' => Status::CLOSED]);
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('GEMEINSAM AN DER SPITZE')->assertSee('Alex &amp; Sam', false)->assertSee('Geteilter Turniersieg');
});

test('the set catalog refreshes daily, filters digital products and retains data through outages', function () {
    Cache::flush();
    Http::fake(['api.scryfall.com/sets' => Http::response(['data' => [
        ['code' => 'old', 'name' => 'Older', 'set_type' => 'core', 'released_at' => '2020-01-01', 'digital' => false],
        ['code' => 'new', 'name' => 'Newest', 'set_type' => 'expansion', 'released_at' => '2026-10-01', 'digital' => false],
        ['code' => 'dig', 'name' => 'Digital', 'set_type' => 'expansion', 'released_at' => '2026-10-02', 'digital' => true],
        ['code' => 'tok', 'name' => 'Tokens', 'set_type' => 'token', 'released_at' => '2026-10-02', 'digital' => false],
    ]])]);
    $catalog = app(MagicSetCatalog::class);
    expect(array_column($catalog->sets(), 'code'))->toBe(['new', 'old']);
    $catalog->sets();
    Http::assertSentCount(1);
    $this->travel(25)->hours();
    Http::fake(['api.scryfall.com/sets' => Http::response([], 503)]);
    expect(array_column($catalog->sets(), 'code'))->toBe(['new', 'old']);
});

test('sealed is the default and a selected set is validated and saved by name', function () {
    Cache::flush();
    Http::fake(['api.scryfall.com/sets' => Http::response(['data' => [
        ['code' => 'tst', 'name' => 'Test Set', 'set_type' => 'expansion', 'released_at' => '2026-01-01', 'digital' => false],
    ]])]);
    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(Tournaments::class)->call('openCreateModal')->assertSet('format', 'sealed')
        ->set('name', 'Friday')->set('setCode', 'invalid')->call('createTournament')->assertHasErrors('setCode')
        ->set('setCode', 'tst')->call('createTournament')->assertHasNoErrors();
    $event = Tournament::first();
    expect($event->format)->toBe('sealed');
    expect($event->set_code)->toBe('tst');
    expect($event->set_name)->toBe('Test Set');
    $this->get(route('play', $event->invite_token))->assertSee('Test Set');
    Livewire::test(Tournaments::class)->call('openEditModal', $event->id)->set('format', 'constructed')->call('editTournament')->assertHasNoErrors();
    expect($event->refresh()->set_code)->toBeNull();
});

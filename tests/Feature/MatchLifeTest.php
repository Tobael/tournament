<?php

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Events\BoardChanged;
use App\Events\LifeChanged;
use App\Http\Middleware\BroadcastBoardChanges;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

function lifeTable(string $format = 'commander'): array
{
    $event = Tournament::create(['name' => 'Life POC', 'group_id' => Group::firstOrCreate(['name' => 'Friends'])->id, 'format' => $format, 'status' => Status::IN_PROGRESS, 'round_limit' => 3]);
    $players = collect(range(1, $format === 'commander' ? 4 : 2))->map(fn ($i) => $event->users()->create(['user_id' => User::factory()->create(['name' => 'Seat '.$i])->id]));
    $round = $event->rounds()->create(['round' => 1]);
    $match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id]);
    if ($format === 'commander') {
        $match->players()->attach($players->pluck('id'));
    }

    return [$event, $players, $match];
}

function lifeUrl($event, $match): string
{
    return route('play.life.update', [$event->invite_token, $match->id]);
}

test('a shared counter starts at format life and supports spectators without write access', function () {
    foreach (['commander' => 40, 'constructed' => 20, 'sealed' => 20] as $format => $initial) {
        [$event, $players, $match] = lifeTable($format);
        $this->get(route('play.life', [$event->invite_token, $match->id]))->assertOk()->assertSee('Zuschauermodus')->assertSee('data-life-rotate', false)->assertDontSee('data-life-delta="5"', false)->assertDontSee('data-life-delta="-5"', false);
        $this->getJson(route('play.life.state', [$event->invite_token, $match->id]))->assertJsonPath('players.0.life', $initial);
        $this->postJson(lifeUrl($event, $match), ['action' => 'life', 'player' => $players[0]->id, 'delta' => -1])->assertForbidden();
        $this->actingAs($players[0]->user)->get(route('play', $event->invite_token))->assertSee('Lebenszähler öffnen');
        $this->postJson(lifeUrl($event, $match), ['action' => 'life', 'player' => $players[1]->id, 'delta' => -5])->assertOk()->assertJsonPath('players.1.life', $initial - 5)->assertJsonPath('revision', 1);
        auth()->logout();
    }
});

test('commander damage is per opponent and updates life atomically with a broadcast', function () {
    Event::fake([LifeChanged::class]);
    [$event, $players, $match] = lifeTable();
    $this->actingAs($players[3]->user);
    $command = ['action' => 'damage', 'player' => $players[0]->id, 'source' => $players[1]->id, 'delta' => 5];
    $this->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('players.0.life', 35);
    $this->postJson(lifeUrl($event, $match), [...$command, 'delta' => 1])->assertOk()->assertJsonPath('players.0.life', 34);
    $this->postJson(lifeUrl($event, $match), [...$command, 'delta' => -5])->assertOk()->assertJsonPath('players.0.life', 39);
    $this->postJson(lifeUrl($event, $match), [...$command, 'delta' => -1])->assertOk()->assertJsonPath('players.0.life', 40);
    $this->postJson(lifeUrl($event, $match), [...$command, 'commander' => 1])->assertUnprocessable();
    expect($match->fresh()->life_state[$players[0]->id]['damage'])->toBe([$players[1]->id => 0]);
    expect($match->fresh()->isCompleted())->toBeFalse();
    Event::assertDispatched(LifeChanged::class, fn ($event) => $event->state['revision'] === 4);
    $html = $this->getJson(route('play.tv.state', $event->invite_token))->assertOk()->json('html');
    expect($html)->toContain('data-tv-life-total>40');
});

test('reset rejects stale revisions and a new game resets every seat and damage', function () {
    [$event, $players, $match] = lifeTable();
    $this->actingAs($players[0]->user);
    $this->postJson(lifeUrl($event, $match), ['action' => 'life', 'player' => $players[0]->id, 'delta' => -5])->assertOk();
    $this->postJson(lifeUrl($event, $match), ['action' => 'reset', 'revision' => 0])->assertConflict();
    $this->postJson(lifeUrl($event, $match), ['action' => 'reset', 'revision' => 1])->assertOk()->assertJsonPath('players.0.life', 40)->assertJsonPath('revision', 2);
});

test('counter rejects foreign seats, foreign tables, invalid commands and finished rounds', function () {
    [$event, $players, $match] = lifeTable();
    [$other, $outsiders, $foreign] = lifeTable();
    $this->actingAs($outsiders[0]->user);
    $command = ['action' => 'life', 'player' => $players[0]->id, 'delta' => -1];
    $this->postJson(lifeUrl($event, $match), $command)->assertForbidden();
    $this->actingAs($players[0]->user);
    $this->postJson(lifeUrl($event, $foreign), $command)->assertNotFound();
    $this->postJson(lifeUrl($event, $match), [...$command, 'player' => $outsiders[0]->id])->assertUnprocessable();
    $this->postJson(lifeUrl($event, $match), [...$command, 'delta' => 100])->assertUnprocessable();
    $this->postJson(lifeUrl($event, $match), ['action' => 'damage', 'player' => $players[0]->id, 'source' => $players[0]->id, 'delta' => 1])->assertUnprocessable();
    $event->rounds()->create(['round' => 2]);
    $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
    $event->rounds()->where('round', 2)->delete();
    $match->update(['commander_scores' => [$players[0]->id => 3]]);
    $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
    $match->update(['commander_scores' => null]);
    $event->update(['status' => Status::CLOSED]);
    $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
});

test('recovered participants and organizers can use the shared table', function () {
    [$event, $players, $match] = lifeTable();
    $players[2]->forceFill(['recovery_hash' => hash('sha256', 'phone-life')])->save();
    $this->withSession(['participants.'.$event->id => ['id' => $players[2]->id, 'token' => 'phone-life']]);
    $command = ['action' => 'life', 'player' => $players[0]->id, 'delta' => -1];
    $this->postJson(lifeUrl($event, $match), $command)->assertOk();
    $this->flushSession();
    $this->actingAs(User::factory()->admin()->create())->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('players.0.life', 38);
});

test('read-only Livewire refresh does not emit a board change loop', function () {
    Event::fake([BoardChanged::class]);
    [$event] = lifeTable();
    Livewire::test(App\Livewire\Tournament::class, ['tournament' => $event])->call('refresh')->assertOk();
    Event::assertNotDispatched(BoardChanged::class);
});

test('hashed Livewire update routes broadcast mutations but never refresh requests', function () {
    Event::fake([BoardChanged::class]);
    $route = app('router')->getRoutes()->getByName('default-livewire.update');
    expect($route)->not->toBeNull();
    $middleware = new BroadcastBoardChanges;
    foreach (['refresh', '$refresh'] as $method) {
        $request = Request::create('/'.$route->uri(), 'POST', ['components' => [['calls' => [['method' => $method]]]]]);
        $request->setRouteResolver(fn () => $route);
        $middleware->handle($request, fn () => response()->json([]));
    }
    Event::assertNotDispatched(BoardChanged::class);
    $request = Request::create('/'.$route->uri(), 'POST', ['components' => [['calls' => [['method' => 'reportCommanderWinner']]]]]);
    $request->setRouteResolver(fn () => $route);
    $middleware->handle($request, fn () => response()->json([]));
    Event::assertDispatchedTimes(BoardChanged::class, 1);
});

test('gesture damage applies one bounded amount atomically to the chosen recipient', function () {
    Event::fake([LifeChanged::class]);
    [$event, $players, $match] = lifeTable();
    $this->actingAs($players[0]->user);
    $this->get(route('play.life', [$event->invite_token, $match->id]))->assertOk()
        ->assertSee('data-damage-step="-1"', false)->assertSee('data-damage-step="1"', false)
        ->assertDontSee('data-damage-delta', false)->assertDontSee('<select data-damage-recipient', false)
        ->assertSee('life-damage-readout', false);
    $command = ['action' => 'damage', 'source' => $players[0]->id, 'player' => $players[1]->id, 'delta' => 7];
    $this->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('players.1.life', 33)->assertJsonPath('revision', 1);
    expect($match->fresh()->life_state[$players[1]->id]['damage'][$players[0]->id])->toBe(7);
    $this->postJson(lifeUrl($event, $match), [...$command, 'delta' => 100])->assertUnprocessable();
    $this->postJson(lifeUrl($event, $match), [...$command, 'delta' => 0])->assertUnprocessable();
    $this->postJson(lifeUrl($event, $match), [...$command, 'player' => $players[0]->id])->assertUnprocessable();
    expect($match->fresh()->life_revision)->toBe(1);
    Event::assertDispatchedTimes(LifeChanged::class, 1);
});

test('confirmed last survivor records the commander match winner once and broadcasts the result', function () {
    Event::fake([LifeChanged::class, BoardChanged::class]);
    [$event, $players, $match] = lifeTable();
    $match->forceFill(['life_state' => $players->mapWithKeys(fn ($player, $index) => [$player->id => ['life' => $index === 2 ? 12 : ($index === 0 ? -1 : 0), 'damage' => []]])->all(), 'life_revision' => 10])->save();
    $command = ['action' => 'finish', 'winner' => $players[2]->id, 'revision' => 10];
    $this->postJson(lifeUrl($event, $match), $command)->assertForbidden();
    $this->actingAs($players[0]->user);
    $this->postJson(lifeUrl($event, $match), [...$command, 'revision' => 9])->assertConflict();
    $this->postJson(lifeUrl($event, $match), [...$command, 'winner' => $players[0]->id])->assertConflict();
    expect($match->fresh()->commander_scores)->toBeNull();
    $this->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('completed', true)->assertJsonPath('editable', false)->assertJsonPath('revision', 11)
        ->assertJsonPath('returnUrl', route('play', $event->invite_token).'#my-match');
    expect($match->fresh()->commander_scores)->toBe([$players[0]->id => 0, $players[1]->id => 0, $players[2]->id => 3, $players[3]->id => 0]);
    $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
    Event::assertDispatchedTimes(LifeChanged::class, 1);
    Event::assertDispatchedTimes(BoardChanged::class, 1);
});

test('Sealed records one game at a time, persists the score and finishes only at two wins', function () {
    foreach ([[0, 0], [1, 1], [0, 1, 0], [1, 0, 1]] as $sequence) {
        [$event, $players, $match] = lifeTable('sealed');
        $this->actingAs($players[0]->user);
        $deadline = now()->addMinutes(50);
        $match->update(['timer_ends_at' => $deadline]);
        $this->get(route('play.life', [$event->invite_token, $match->id]))->assertOk()->assertDontSee('data-life-win-score', false)->assertDontSee('data-damage-handle', false);
        $wins = [0, 0];
        foreach ($sequence as $game => $winnerIndex) {
            $match->refresh()->forceFill(['life_state' => $players->mapWithKeys(fn ($player, $index) => [$player->id => ['life' => $index === $winnerIndex ? 5 : 0, 'damage' => []]])->all(), 'life_revision' => $match->life_revision + 1])->save();
            $revision = $match->life_revision;
            $command = ['action' => 'finish', 'winner' => $players[$winnerIndex]->id, 'revision' => $revision];
            $this->postJson(lifeUrl($event, $match), [...$command, 'score' => '2-0'])->assertUnprocessable();
            $wins[$winnerIndex]++;
            $completed = max($wins) === 2;
            $response = $this->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('completed', $completed)
                ->assertJsonPath('players.0.wins', $wins[0])->assertJsonPath('players.1.wins', $wins[1]);
            $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
            $match->refresh();
            expect($match->timer_ends_at->timestamp)->toBe($deadline->timestamp);
            expect($match->game_wins)->toBe([$players[0]->id => $wins[0], $players[1]->id => $wins[1]]);
            if (! $completed) {
                $response->assertJsonPath('operation', 'game-win')->assertJsonPath('players.0.life', 20)->assertJsonPath('players.1.life', 20)->assertJsonPath('gameNumber', $game + 2);
                expect($match->result)->toBeNull();
                expect($event->fresh()->currentRound()->isCompleted())->toBeFalse();
                $this->getJson(route('play.life.state', [$event->invite_token, $match->id]))->assertJsonPath('players.0.wins', $wins[0]);
                $tv = $this->getJson(route('play.tv.state', $event->invite_token))->assertOk()->json('html');
                expect($tv)->toContain('Spiele '.$wins[0].'–'.$wins[1]);
            } else {
                expect($match->result)->toBe(RoundMatchResult::from($wins[0].'-'.$wins[1]));
            }
        }
    }
});

test('winner confirmation rejects revived opponents and tables with no survivor', function () {
    [$event, $players, $match] = lifeTable();
    $this->actingAs($players[0]->user);
    $command = ['action' => 'finish', 'winner' => $players[0]->id, 'revision' => 0];
    $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
    $match->forceFill(['life_state' => $players->mapWithKeys(fn ($player) => [$player->id => ['life' => 0, 'damage' => []]])->all()])->save();
    $this->postJson(lifeUrl($event, $match), $command)->assertConflict();
    expect($match->fresh()->isCompleted())->toBeFalse();
});

test('starting from the counter creates one shared deadline and never resets the running match timer', function () {
    Event::fake([LifeChanged::class, BoardChanged::class]);
    $this->travelTo(now()->startOfSecond());
    [$event, $players, $match] = lifeTable();
    $command = ['action' => 'timer', 'minutes' => 90];
    $this->postJson(lifeUrl($event, $match), $command)->assertForbidden();
    $this->actingAs($players[0]->user);
    $this->postJson(lifeUrl($event, $match), [...$command, 'minutes' => 1])->assertUnprocessable();
    $expected = now()->addMinutes(90)->getTimestampMs();
    $this->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('timerEndsAt', $expected)->assertJsonPath('revision', 1)->assertJsonPath('players.0.life', 40);
    $this->travel(3)->minutes();
    $this->postJson(lifeUrl($event, $match), [...$command, 'minutes' => 25])->assertOk()->assertJsonPath('timerEndsAt', $expected)->assertJsonPath('revision', 1);
    $this->postJson(lifeUrl($event, $match), ['action' => 'reset', 'revision' => 1])->assertOk()->assertJsonPath('timerEndsAt', $expected);
    expect($match->fresh()->isCompleted())->toBeFalse();
    Event::assertDispatchedTimes(BoardChanged::class, 1);
    Event::assertDispatchedTimes(LifeChanged::class, 2);
});

test('resetting life between games preserves confirmed wins and cannot erase the match score', function () {
    [$event, $players, $match] = lifeTable('sealed');
    $this->actingAs($players[0]->user);
    $match->forceFill(['game_wins' => [$players[0]->id => 1, $players[1]->id => 0]])->save();
    $this->postJson(lifeUrl($event, $match), ['action' => 'reset', 'revision' => 0])->assertOk()
        ->assertJsonPath('players.0.life', 20)->assertJsonPath('players.0.wins', 1)->assertJsonPath('completed', false);
});

test('commander damage elimination triggers the same confirmed survivor result', function () {
    [$event, $players, $match] = lifeTable();
    $this->actingAs($players[0]->user);
    $match->forceFill(['life_state' => $players->mapWithKeys(fn ($player, $index) => [$player->id => ['life' => 10, 'damage' => $index === 0 ? [] : [$players[0]->id => 21]]])->all()])->save();
    $this->postJson(lifeUrl($event, $match), ['action' => 'finish', 'winner' => $players[0]->id, 'revision' => 0])->assertOk()->assertJsonPath('completed', true);
});

test('direct life entry validates permission and revision and preserves damage, score and timer', function () {
    Event::fake([LifeChanged::class]);
    foreach (['commander', 'sealed'] as $format) {
        [$event, $players, $match] = lifeTable($format);
        auth()->logout();
        $command = ['action' => 'set-life', 'player' => $players[1]->id, 'value' => 42, 'revision' => 2];
        $this->postJson(lifeUrl($event, $match), $command)->assertForbidden();
        $deadline = now()->addMinutes(50);
        $wins = $format === 'sealed' ? [$players[0]->id => 1, $players[1]->id => 0] : null;
        $damage = $format === 'commander' ? [$players[0]->id => 3] : [];
        $match->forceFill(['life_state' => [$players[1]->id => ['life' => 10, 'damage' => $damage]], 'life_revision' => 2, 'timer_ends_at' => $deadline, 'game_wins' => $wins])->save();
        $this->actingAs($players[0]->user);
        $this->postJson(lifeUrl($event, $match), $command)->assertOk()->assertJsonPath('players.1.life', 42)->assertJsonPath('revision', 3)->assertJsonPath('operation', 'set-life');
        $this->postJson(lifeUrl($event, $match), [...$command, 'value' => 5])->assertConflict();
        foreach ([-1000, 10000, 1.5] as $value) {
            $this->postJson(lifeUrl($event, $match), [...$command, 'revision' => 3, 'value' => $value])->assertUnprocessable();
        }
        $this->postJson(lifeUrl($event, $match), [...$command, 'revision' => 3, 'player' => 999999])->assertUnprocessable();
        $match->refresh();
        expect($match->life_state[$players[1]->id])->toBe(['life' => 42, 'damage' => $damage]);
        expect($match->game_wins)->toBe($wins);
        expect($match->timer_ends_at->timestamp)->toBe($deadline->timestamp);
        $this->postJson(lifeUrl($event, $match), [...$command, 'revision' => 3, 'value' => 0])->assertOk()->assertJsonPath('completed', false);
        expect($match->fresh()->result)->toBeNull();
    }
    Event::assertDispatchedTimes(LifeChanged::class, 4);
});

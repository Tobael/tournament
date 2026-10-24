<?php

use App\Enums\Status;
use App\Livewire\Tournament as TournamentPage;
use App\Livewire\Tournaments;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use App\Services\CommanderTournamentService;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function commanderEvent(int $count = 7, array $options = []): Tournament
{
    $event = Tournament::create(array_merge([
        'name' => 'Commander Night', 'format' => 'commander', 'status' => Status::OPEN,
        'group_id' => Group::firstOrCreate(['name' => 'Commander Friends'])->id,
        'table_preference' => 'balanced', 'round_limit' => 3,
    ], $options));
    foreach (range(1, $count) as $i) {
        $event->users()->create(['user_id' => User::factory()->create(['name' => 'Player '.$i])->id]);
    }

    return $event;
}

function startCommanderRound(Tournament $event): void
{
    test()->actingAs(User::factory()->admin()->create());
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startNextRound')->assertOk();
}

test('all table preferences seat every player without byes across awkward player counts', function () {
    $service = app(CommanderTournamentService::class);
    foreach (['balanced', 'small', 'large'] as $preference) {
        foreach (range(2, 50) as $count) {
            $sizes = $service->tableSizes($count, $preference);
            expect(array_sum($sizes))->toBe($count);
            expect(min($sizes))->toBeGreaterThanOrEqual($count === 2 ? 2 : 3);
            expect(max($sizes))->toBeLessThanOrEqual(5);
        }
    }
    expect($service->tableSizes(1))->toBe([]);
    expect($service->tableSizes(6))->toBe([3, 3]);
    expect($service->tableSizes(9, 'small'))->toBe([3, 3, 3]);
    expect($service->tableSizes(10, 'large'))->toBe([5, 5]);
    expect($service->tableSizes(8))->toBe([4, 4]);
});

test('commander automatically assigns everyone once and shows their table without deck fields', function () {
    $event = commanderEvent();
    startCommanderRound($event);
    $round = $event->fresh()->currentRound();
    expect($round->matches()->withCount('players')->get()->pluck('players_count')->sort()->values()->all())->toBe([3, 4]);
    $assigned = $round->matches()->with('players')->get()->flatMap->players;
    expect($assigned->pluck('id')->unique())->toHaveCount(7);
    expect($assigned->pluck('id')->sort()->values()->all())->toBe($event->users()->pluck('id')->sort()->values()->all());
    $table = $round->matches()->with('players')->first();
    $player = $table->players->first(fn ($player) => ! in_array($player->id, [$table->player_a_id, $table->player_b_id]));
    auth()->logout();
    $this->actingAs($player->user)->get(route('play', $event->invite_token))->assertOk()->assertSee('Dein Tisch')->assertSee('Wer hat gewonnen?')->assertDontSee('Dein Sieg')->assertDontSee('Kein Deck angegeben');
    $open = commanderEvent(2);
    auth()->logout();
    $this->get(route('play', $open->invite_token))->assertOk()->assertDontSee('name="deckname"', false);
    $this->post(route('play.join', $open->invite_token), ['name' => 'New Player', 'deckname' => 'Ignored deck'])->assertRedirect();
    expect($open->users()->latest('id')->first()->getRawOriginal('deckname'))->toBeNull();
});

test('only table players and organizers can report and the first commander result wins', function () {
    $event = commanderEvent();
    startCommanderRound($event);
    $tables = $event->fresh()->currentRound()->matches()->with('players.user')->get();
    $table = $tables[0];
    $winner = $table->players->last();
    auth()->logout();
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderWinner', $table->id, $winner->id)->assertForbidden();
    $this->actingAs($tables[1]->players->first()->user);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderWinner', $table->id, $winner->id)->assertForbidden();
    $this->actingAs($winner->user);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderWinner', $table->id, $tables[1]->players->first()->id)->assertStatus(422);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('updateCurrentMatchForUser', '2-0')->assertStatus(422);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderWinner', $table->id, $winner->id)->assertOk();
    expect($table->refresh()->commander_scores[$winner->id])->toBe(3);
    expect(array_sum($table->commander_scores))->toBe(3);
    expect($winner->fresh()->points())->toBe(3);
    expect($winner->fresh()->games())->toBe(1);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderDraw', $table->id)->assertStatus(409);
    // A finished table cannot start a timer.
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startMatchTimer', $table->id, 90)->assertOk();
    expect($table->refresh()->timer_ends_at)->toBeNull();
});

test('timeouts award only selected survivors and reject early or invalid results', function () {
    $event = commanderEvent(4);
    startCommanderRound($event);
    $table = $event->fresh()->currentRound()->matches()->with('players')->first();
    $ids = $table->players->modelKeys();
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderTimeout', $table->id, [$ids[0]])->assertStatus(422);
    $table->update(['timer_ends_at' => now()->subMinute()]);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderTimeout', $table->id, [999999])->assertHasErrors('survivors.0');
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderTimeout', $table->id, [$ids[0], $ids[0]])->assertHasErrors('survivors.0');
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderTimeout', $table->id, [strval($ids[0]), strval($ids[2])])->assertOk();
    expect($table->refresh()->commander_scores)->toBe([$ids[0] => 1, $ids[1] => 0, $ids[2] => 1, $ids[3] => 0]);
    expect($event->fresh()->currentRound()->isCompleted())->toBeTrue();
    expect($event->fresh()->standings()->where('place', 1))->toHaveCount(2);
    $event->update(['status' => Status::CLOSED]);
    $this->get(route('play', $event->invite_token))->assertOk()->assertSee('GEMEINSAM AN DER SPITZE');
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderWinner', $table->id, $ids[0])->assertStatus(422);
});

test('rounds minimize repeat opponents and stop at the configured limit', function () {
    $event = commanderEvent(8, ['round_limit' => 2]);
    startCommanderRound($event);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startNextRound')->assertStatus(422);
    $first = $event->fresh()->currentRound()->matches()->with('players')->get();
    foreach ($first as $table) {
        Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderDraw', $table->id)->assertOk();
    }
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startNextRound')->assertOk();
    $second = $event->fresh()->currentRound()->matches()->with('players')->get();
    $repeatedPairs = 0;
    foreach ($first as $a) {
        foreach ($second as $b) {
            $overlap = count(array_intersect($a->players->modelKeys(), $b->players->modelKeys()));
            $repeatedPairs += $overlap * ($overlap - 1) / 2;
        }
    }
    expect($repeatedPairs)->toBe(4); // Mathematical minimum for two rounds of 4+4.
    foreach ($second as $table) {
        Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderDraw', $table->id)->assertOk();
    }
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('startNextRound')->assertStatus(422);
    Livewire::test(TournamentPage::class, ['tournament' => $event])->call('reportCommanderDraw', $first[0]->id)->assertNotFound();
    $this->get(route('play', $event->invite_token))->assertSee('Zeit für die Siegerehrung!');
});

test('commander settings persist but cannot change after round generation', function () {
    $this->actingAs(User::factory()->admin()->create());
    // Set selection is irrelevant to commander and does not require a catalog request.
    Livewire::test(Tournaments::class)->set('name', 'Pods')->set('format', 'commander')->set('tablePreference', 'small')->set('roundLimit', 4)->call('createTournament')->assertHasNoErrors();
    $event = Tournament::first();
    expect($event->table_preference)->toBe('small');
    expect($event->round_limit)->toBe(4);
    $event->users()->create(['user_id' => User::factory()->create()->id]);
    $event->users()->create(['user_id' => User::factory()->create()->id]);
    startCommanderRound($event);
    // Seed a catalog cache so editing does not need the network.
    Cache::put('magic-sets.fresh', true, 60);
    Livewire::test(Tournaments::class)->call('openEditModal', $event->id)->set('format', 'sealed')->call('editTournament')->assertHasErrors('format');
    Livewire::test(Tournaments::class)->call('openEditModal', $event->id)->set('roundLimit', 10)->call('editTournament')->assertHasErrors('roundLimit');
    Livewire::test(Tournaments::class)->call('openEditModal', $event->id)->set('name', 'Renamed')->call('editTournament')->assertHasNoErrors();
});

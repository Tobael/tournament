<?php

use App\Enums\Status;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use App\Services\MatchLife;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array', 'broadcasting.default' => 'null', 'broadcasting.connections.reverb.key' => 'commentary-test', 'broadcasting.browser_port' => 8095]);
DB::purge();
Artisan::call('migrate', ['--force' => true]);
URL::forceRootUrl('http://127.0.0.1:8095');
app('session')->start();
$event = Tournament::create(['name' => 'Friday Night Magic', 'group_id' => Group::create(['name' => 'Arcane Table'])->id, 'format' => 'commander', 'status' => Status::IN_PROGRESS, 'round_limit' => 3]);
$players = collect(['Tobi', 'Hannah', 'Max', 'Lena'])->map(fn ($name) => $event->users()->create(['user_id' => User::factory()->create(['name' => $name])->id]));
$round = $event->rounds()->create(['round' => 1]);
$match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id, 'timer_ends_at' => now()->addMinutes(90)]);
$match->players()->attach($players->pluck('id'));
$event->load(['group', 'users.user', 'matches', 'rounds.matches.playerA.user', 'rounds.matches.playerB.user', 'rounds.matches.players.user']);
$match = $event->currentRound()->matches->first();
$state = app(MatchLife::class)->snapshot($event, $match);
$fixtures = ['state' => $state];
foreach (['de', 'en'] as $locale) {
    app()->setLocale($locale);
    $fixtures['life-'.$locale] = view('match-life', ['tournament' => $event, 'match' => $match, 'state' => $state, 'canControl' => true])->render();
    $fixtures['tv-'.$locale] = view('tournament-tv', ['tournament' => $event, 'standings' => $event->standings(), 'currentRound' => $event->currentRound()])->render();
}
echo json_encode($fixtures, JSON_THROW_ON_ERROR);

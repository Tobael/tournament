<?php

use App\Enums\Status;
use App\Livewire\Tournaments;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function populatedDeletionTournament(string $format, $accounts): Tournament
{
    $event = Tournament::create(['name' => 'Delete '.$format, 'group_id' => Group::firstOrCreate(['name' => 'Deletion tests'])->id, 'format' => $format, 'status' => Status::IN_PROGRESS]);
    $players = $accounts->map(fn ($account) => $event->users()->create(['user_id' => $account->id]));
    foreach ([1, 2] as $number) {
        $round = $event->rounds()->create(['round' => $number]);
        $match = $round->matches()->create(['player_a_id' => $players[0]->id, 'player_b_id' => $players[1]->id]);
        if ($format === 'commander') {
            $match->players()->attach($players->pluck('id'));
        }
    }

    return $event;
}

test('organizers can delete tournaments with rounds and participants while preserving accounts and other events', function (string $format) {
    $this->withoutVite();
    $organizer = User::factory()->admin()->create();
    $accounts = User::factory()->count(4)->create();
    $event = populatedDeletionTournament($format, $accounts);
    $other = populatedDeletionTournament($format, $accounts);
    $matchIds = $event->matches()->pluck('round_matches.id');

    Livewire::actingAs($organizer)->test(Tournaments::class)->call('openDeleteModal', $event->id)->call('deleteTournament')->assertOk();

    expect(Tournament::find($event->id))->toBeNull();
    expect(DB::table('rounds')->where('tournament_id', $event->id)->count())->toBe(0);
    expect(DB::table('tournament_users')->where('tournament_id', $event->id)->count())->toBe(0);
    expect(DB::table('round_matches')->whereIn('id', $matchIds)->count())->toBe(0);
    expect(DB::table('round_match_players')->whereIn('round_match_id', $matchIds)->count())->toBe(0);
    expect($other->fresh()->matches()->count())->toBe(2);
    expect($other->users()->count())->toBe(4);
    expect(User::whereIn('id', $accounts->pluck('id'))->count())->toBe(4);
    expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
})->with(['sealed', 'commander']);

test('cascade repair upgrades anonymous legacy SQLite constraints without losing rows', function () {
    $original = DB::getDefaultConnection();
    config(['database.connections.deletion_legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    DB::setDefaultConnection('deletion_legacy');
    try {
        DB::unprepared('CREATE TABLE users (id INTEGER PRIMARY KEY);
            CREATE TABLE tournaments (id INTEGER PRIMARY KEY);
            CREATE TABLE tournament_users (id INTEGER PRIMARY KEY, tournament_id INTEGER NOT NULL REFERENCES tournaments(id), user_id INTEGER NOT NULL REFERENCES users(id));
            CREATE TABLE rounds (id INTEGER PRIMARY KEY, tournament_id INTEGER NOT NULL REFERENCES tournaments(id));
            CREATE TABLE round_matches (id INTEGER PRIMARY KEY, round_id INTEGER NOT NULL REFERENCES rounds(id), player_a_id INTEGER NOT NULL REFERENCES tournament_users(id), player_b_id INTEGER REFERENCES tournament_users(id), life_state TEXT);
            CREATE TABLE round_match_players (id INTEGER PRIMARY KEY, round_match_id INTEGER REFERENCES round_matches(id) ON DELETE CASCADE, tournament_user_id INTEGER REFERENCES tournament_users(id) ON DELETE CASCADE);
            INSERT INTO users VALUES (1), (2);
            INSERT INTO tournaments VALUES (7), (8);
            INSERT INTO tournament_users VALUES (1, 7, 1), (2, 7, 2), (3, 8, 1);
            INSERT INTO rounds VALUES (1, 7), (2, 8);
            INSERT INTO round_matches VALUES (1, 1, 1, 2, \'{"1":{"life":33}}\');
            INSERT INTO round_match_players VALUES (1, 1, 1), (2, 1, 2);');
        $migration = require database_path('migrations/2026_11_11_000000_repair_tournament_delete_cascades.php');
        $migration->up();
        expect(DB::table('round_matches')->value('life_state'))->toBe('{"1":{"life":33}}');
        expect(DB::table('round_match_players')->count())->toBe(2);
        $migration->up(); // Already-correct databases need no rebuild.
        DB::table('tournaments')->where('id', 7)->delete();
        expect(DB::table('round_matches')->count())->toBe(0);
        expect(DB::table('round_match_players')->count())->toBe(0);
        expect(DB::table('users')->count())->toBe(2);
        expect(DB::table('rounds')->where('tournament_id', 8)->count())->toBe(1);
        expect(DB::select('PRAGMA foreign_key_check'))->toBe([]);
        expect((int) DB::scalar('PRAGMA foreign_keys'))->toBe(1);
    } finally {
        DB::setDefaultConnection($original);
        DB::purge('deletion_legacy');
    }
});

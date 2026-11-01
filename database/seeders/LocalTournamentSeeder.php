<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\User;
use App\Services\CommanderTournamentService;
use App\Services\SwissTournamentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalTournamentSeeder extends Seeder
{
    public function run(CommanderTournamentService $commander, SwissTournamentService $swiss): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Local tournament fixtures can only be seeded locally or in tests.');
        }

        DB::transaction(function () use ($commander, $swiss) {
            // Keep the existing organizer's credentials and include them as a player.
            $organizer = User::where('is_admin', true)->orderBy('id')->first()
                ?? User::factory()->admin()->withoutTwoFactor()->create([
                    'name' => 'Local Organizer',
                    'email' => 'organizer@tournament.test',
                    'password' => 'local-dev-password',
                ]);
            $players = collect([$organizer]);
            foreach (['Alex', 'Sam', 'Kim', 'Robin', 'Charlie', 'Jamie', 'Morgan'] as $index => $name) {
                $players->push(User::firstOrCreate(['email' => 'local-player-'.$index.'@tournament.test'], [
                    'name' => $name, 'password' => 'local-dev-password', 'is_admin' => false,
                ]));
            }
            $group = Group::firstOrCreate(['name' => 'Lokale Testrunde']);
            foreach ([['commander', 3], ['commander', 4], ['commander', 5], ['sealed', 4]] as [$format, $count]) {
                $name = 'Lokaler Test · '.ucfirst($format).' · '.$count.' Personen';
                // Re-running preserves games and counters; app:seed-local --reset rebuilds them.
                if (Tournament::where('group_id', $group->id)->where('name', $name)->exists()) {
                    continue;
                }
                $event = Tournament::create([
                    'name' => $name, 'group_id' => $group->id, 'format' => $format,
                    'status' => Status::OPEN, 'table_preference' => 'balanced', 'round_limit' => 3,
                    'scheduled_on' => today()->format('Y-m-d'),
                ]);
                foreach ($players->take($count) as $player) {
                    $event->users()->create(['user_id' => $player->id]);
                }
                ($format === 'commander' ? $commander : $swiss)->generateNextRound($event);
                $event->update(['status' => Status::IN_PROGRESS]);
            }
            $this->command?->info('Local fixtures ready: Commander with 3, 4 and 5 players; Sealed with 4 players. Organizer '.$organizer->email.' is seated in all four.');
        });
    }
}

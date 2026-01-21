<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Tournament;
use App\Models\TournamentUser;
use App\Models\User;
use App\Services\SwissTournamentService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(SwissTournamentService $swissTournamentService): void
    {
        User::factory()->admin()->withoutTwoFactor()->create([
            'name' => 'Admin',
            'email' => 'admin@tournaments.com',
        ]);

        User::factory()->count(4)->create();

        $group = Group::factory()->create([
            'name' => 'Kellerkinder',
        ]);

        $tournament = Tournament::factory()->for($group)->create();

        foreach (User::all() as $user) {
            TournamentUser::factory()->for($user)->for($tournament)->create();
        }

        $swissTournamentService->generateNextRound($tournament);
    }
}

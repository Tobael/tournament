<?php

namespace App;

use App\Enums\RoundMatchResult;
use App\Models\Round;
use App\Models\RoundMatch;
use App\Models\Tournament;
use App\Models\TournamentUser;

class SwissTournamentHandler
{
    private function __construct(
        public Tournament $tournament,
    ) {}

    public static function create(Tournament $tournament): self
    {
        return new self($tournament);
    }

    public function generateNextRound(): void
    {
        $users = $this->tournament->users->shuffle();
        $all = clone($users);

        $round = Round::create([
            'round' => ($this->tournament->rounds->max('round') ?? 0) + 1,
            'tournament_id' => $this->tournament->id,
        ]);

        while ($users->isNotEmpty()) {
            $user = $users->first();

            $matches = $user
                ->tournament
                ->matches()
                ->hasUser($user)
                ->get();

            $alreadyPlayed = $matches
                ->map(fn(RoundMatch $rm) => [$rm->player_a_id, $rm->player_b_id])
                ->flatten()
                ->filter()
                ->unique();

            $notPlayed = $users
                ->filter(fn(TournamentUser $tuser) => $user->id != $tuser->id && !$alreadyPlayed->contains($tuser->id));

            $result = null;

            if ($notPlayed->isEmpty()) {
                if ($all->count() % 2 == 0) {
                    dd('HIER LÄUFT WAS RICHTIG SCHIEF');
                } else {
                    $opponentId = null;
                    $result = RoundMatchResult::TWOZERO;
                }
            } else {
                $opponentId = $notPlayed
                    ->sortBy(fn(TournamentUser $tuser) => abs($tuser->points() - $user->points()))
                    ->first()
                    ->id;
            }

            RoundMatch::create([
                'result' => $result,
                'round_id' => $round->id,
                'player_a_id' => $user->id,
                'player_b_id' => $opponentId,
            ]);

            $users = $users->filter(fn(TournamentUser $tuser) => !in_array($tuser->id, [$user->id, $opponentId]));
        }
    }
}

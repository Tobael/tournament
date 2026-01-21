<?php

namespace App\Services;

use App\Enums\RoundMatchResult;
use App\Models\Round;
use App\Models\RoundMatch;
use App\Models\Tournament;
use App\Models\TournamentUser;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\DB;

#[Singleton]
class SwissTournamentService
{
    public function generateNextRound(Tournament $tournament): bool
    {
        DB::beginTransaction();

        $users = $tournament->users->values();

        if ($users->isEmpty()) {
            DB::rollBack();

            return false;
        }

        $playedAgainst = [];

        foreach ($tournament->matches as $match) {
            if ($match->player_a_id && $match->player_b_id) {
                $playedAgainst[$match->player_a_id][$match->player_b_id] = true;
                $playedAgainst[$match->player_b_id][$match->player_a_id] = true;
            }
        }

        $users = $users->sortByDesc(fn($tuser) => $tuser->points())->values();

        $round = Round::create([
            'round' => ($tournament->rounds->max('round') ?? 0) + 1,
            'tournament_id' => $tournament->id,
        ]);

        $pairings = collect();

        if ($users->count() % 2 === 1) {
            $byeUser = $users
                ->reverse()
                ->first(fn($tuser) => !$this->hasByeBefore($tuser));

            if (!$byeUser) {
                $byeUser = $users->last();
            }

            $pairings->push([
                'a' => $byeUser,
                'b' => null,
                'result' => RoundMatchResult::TWOZERO,
            ]);

            $users = $users->reject(fn($tuser) => $tuser->id === $byeUser->id)->values();
        }

        $maxAttempts = 30;
        $attempt = 0;

        while ($attempt++ < $maxAttempts) {
            $remaining = $users->values();
            $currentPairings = collect();
            $failed = false;

            while ($remaining->isNotEmpty()) {
                $user = $remaining->shift();

                $candidates = $remaining->filter(fn($opponent) => empty($playedAgainst[$user->id][$opponent->id]));

                if ($candidates->isEmpty()) {
                    $failed = true;
                    break;
                }

                $opponent = $candidates
                    ->sortBy(fn($tuser) => abs($tuser->points() - $user->points()))
                    ->first();

                $currentPairings->push([
                    'a' => $user,
                    'b' => $opponent,
                    'result' => null,
                ]);

                $remaining = $remaining
                    ->reject(fn($tuser) => $tuser->id === $opponent->id)
                    ->values();
            }

            if (!$failed) {
                $pairings = $pairings->merge($currentPairings);
                break;
            }

            $users = $users->shuffle()->values();
        }

        $userCount = $tournament->users->count();
        $userCount += $userCount % 2;

        if ($pairings->count() * 2 !== $userCount) {
            DB::rollBack();

            return false;
        }

        foreach ($pairings as $pair) {
            RoundMatch::create([
                'round_id' => $round->id,
                'player_a_id' => $pair['a']->id,
                'player_b_id' => $pair['b']?->id,
                'result' => $pair['result'],
            ]);
        }

        DB::commit();

        return true;
    }

    private function hasByeBefore(TournamentUser $tuser): bool
    {
        return $tuser->tournament
            ->matches()
            ->where('player_a_id', $tuser->id)
            ->whereNull('player_b_id')
            ->exists();
    }
}

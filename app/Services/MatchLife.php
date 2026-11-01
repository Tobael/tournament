<?php

namespace App\Services;

use App\Models\RoundMatch;
use App\Models\Tournament;

class MatchLife
{
    public function players(Tournament $tournament, RoundMatch $match)
    {
        return $tournament->format === 'commander' ? $match->players : collect([$match->playerA, $match->playerB])->filter()->values();
    }

    public function snapshot(Tournament $tournament, RoundMatch $match): array
    {
        $players = $this->players($tournament, $match);
        $initial = $tournament->format === 'commander' ? 40 : 20;
        $stored = $match->life_state ?? [];
        $wins = $match->game_wins ?? [];
        $games = array_sum($wins);

        return [
            'matchId' => $match->id,
            'format' => $tournament->format,
            'bestOf' => $tournament->format === 'commander' ? 1 : 3,
            'gameNumber' => $match->isCompleted() ? max(1, $games) : min(3, $games + 1),
            'timerEndsAt' => $match->timer_ends_at?->getTimestampMs(),
            'serverTime' => now()->getTimestampMs(),
            'completed' => $match->isCompleted(),
            'returnUrl' => route('play', $tournament->invite_token).'#my-match',
            'revision' => $match->life_revision,
            'editable' => $match->isLifeCounterActive($tournament),
            'players' => $players->map(fn ($player) => [
                'id' => $player->id,
                'name' => $player->user->name,
                'life' => $stored[$player->id]['life'] ?? $initial,
                'wins' => (int) ($wins[$player->id] ?? 0),
                'damage' => $stored[$player->id]['damage'] ?? [],
            ])->all(),
        ];
    }
}

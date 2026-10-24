<?php

namespace App\Services;

use App\Models\Tournament;

class CommanderTournamentService
{
    /** Every player gets a seat. Two-player tables are a last resort. */
    public function tableSizes(int $count, string $preference = 'balanced'): array
    {
        if ($count < 2) {
            return [];
        }
        $target = match ($preference) {
            'small' => 3, 'large' => 5, default => 4
        };
        $best = [0 => ['cost' => 0, 'sizes' => []]];
        for ($players = 2; $players <= $count; $players++) {
            foreach (range(2, 5) as $size) {
                if (! isset($best[$players - $size])) {
                    continue;
                }
                $cost = $best[$players - $size]['cost'] + ($size === 2 ? 100 : 0) + ($size - $target) ** 2;
                if (! isset($best[$players]) || $cost < $best[$players]['cost']) {
                    $best[$players] = ['cost' => $cost, 'sizes' => [...$best[$players - $size]['sizes'], $size]];
                }
            }
        }

        return $best[$count]['sizes'];
    }

    /** Called while the tournament is locked by the round-start action. */
    public function generateNextRound(Tournament $tournament): bool
    {
        $tournament->loadMissing(['users.user', 'matches.players', 'rounds']);
        $sizes = $this->tableSizes($tournament->users->count(), $tournament->table_preference);
        if (! $sizes || $tournament->rounds->count() >= $tournament->round_limit) {
            return false;
        }
        $history = [];
        foreach ($tournament->matches as $match) {
            foreach ($match->players as $a) {
                foreach ($match->players as $b) {
                    if ($a->id !== $b->id) {
                        $history[$a->id][$b->id] = ($history[$a->id][$b->id] ?? 0) + 1;
                    }
                }
            }
        }
        $points = $tournament->users->mapWithKeys(fn ($player) => [$player->id => $player->points()])->all();
        $bestCost = INF;
        $bestTables = [];
        // Multiple candidates let repeats be minimized without banning later rounds.
        for ($attempt = 0; $attempt < 80; $attempt++) {
            $remaining = $tournament->users->shuffle()->pluck('id')->all();
            $tables = [];
            $cost = 0;
            foreach ($sizes as $size) {
                $table = [array_shift($remaining)];
                while (count($table) < $size) {
                    $candidateCost = fn ($candidate) => array_sum(array_map(
                        fn ($seated) => 100 * ($history[$candidate][$seated] ?? 0) ** 2 + abs($points[$candidate] - $points[$seated]),
                        $table,
                    ));
                    usort($remaining, fn ($a, $b) => $candidateCost($a) <=> $candidateCost($b));
                    $next = array_shift($remaining);
                    $cost += $candidateCost($next);
                    $table[] = $next;
                }
                $tables[] = $table;
            }
            if ($cost < $bestCost) {
                $bestCost = $cost;
                $bestTables = $tables;
            }
        }
        $round = $tournament->rounds()->create(['round' => ($tournament->rounds->max('round') ?? 0) + 1]);
        foreach ($bestTables as $table) {
            shuffle($table);
            $match = $round->matches()->create(['player_a_id' => $table[0], 'player_b_id' => $table[1]]);
            $match->players()->attach($table);
        }

        return true;
    }
}

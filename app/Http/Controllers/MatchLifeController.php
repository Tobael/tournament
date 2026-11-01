<?php

namespace App\Http\Controllers;

use App\Enums\RoundMatchResult;
use App\Events\BoardChanged;
use App\Events\LifeChanged;
use App\Models\RoundMatch;
use App\Models\Tournament;
use App\Services\MatchLife;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatchLifeController extends Controller
{
    private function table(Tournament $tournament, int $match): RoundMatch
    {
        return $tournament->matches()->with(['players.user', 'playerA.user', 'playerB.user'])->findOrFail($match);
    }

    public function show(Tournament $tournament, int $match, MatchLife $life)
    {
        $table = $this->table($tournament, $match);
        $state = $life->snapshot($tournament, $table);
        if (! $state['editable']) {
            return redirect($state['returnUrl'])->header('Cache-Control', 'no-store');
        }
        $participant = $tournament->participant();
        $canControl = auth()->user()?->is_admin || ($participant && $life->players($tournament, $table)->contains('id', $participant->id));

        return response()->view('match-life', ['tournament' => $tournament, 'match' => $table, 'state' => $state, 'canControl' => $canControl])->header('Cache-Control', 'no-store');
    }

    public function state(Tournament $tournament, int $match, MatchLife $life)
    {
        return response()->json($life->snapshot($tournament, $this->table($tournament, $match)))->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Tournament $tournament, int $match, MatchLife $life)
    {
        $table = $this->table($tournament, $match);
        $players = $life->players($tournament, $table);
        $participant = $tournament->participant();
        abort_unless(auth()->user()?->is_admin || ($participant && $players->contains('id', $participant->id)), 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['life', 'set-life', 'damage', 'reset', 'finish', 'timer'])],
            'player' => ['required_unless:action,reset,finish,timer', 'integer', Rule::in($players->pluck('id')->all())],
            'source' => ['required_if:action,damage', 'integer', Rule::in($players->pluck('id')->all())],
            'commander' => ['prohibited'],
            'delta' => $request->input('action') === 'damage'
                ? ['required', 'integer', 'between:-99,99', 'not_in:0']
                : ['required_unless:action,reset,finish,timer,set-life', 'integer', Rule::in([-5, -1, 1, 5])],
            'value' => ['required_if:action,set-life', 'integer', 'between:-999,9999'],
            'minutes' => ['required_if:action,timer', 'integer', Rule::in([25, 50, 60, 90])],
            'winner' => ['required_if:action,finish', 'integer', Rule::in($players->pluck('id')->all())],
            'score' => ['prohibited'],
            'revision' => ['required_if:action,reset,finish,set-life', 'integer', 'min:0'],
        ]);
        abort_if($data['action'] === 'damage' && ($tournament->format !== 'commander' || (int) $data['player'] === (int) $data['source']), 422);
        // Compare-and-swap prevents lost taps from concurrent phones, including SQLite.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $table->refresh();
            $tournament->refresh();
            $state = $life->snapshot($tournament, $table);
            abort_unless($state['editable'], 409, 'Dieser Tisch ist nicht mehr aktiv.');
            abort_if(in_array($data['action'], ['reset', 'finish', 'set-life'], true) && (int) $data['revision'] !== $table->life_revision, 409, 'Der Zähler wurde inzwischen geändert.');
            if ($data['action'] === 'timer') {
                if ($table->timer_ends_at) {
                    return response()->json([...$state, 'operation' => 'timer'])->header('Cache-Control', 'no-store');
                }
                $saved = $table->newQuery()->whereKey($table->id)->where('life_revision', $table->life_revision)
                    ->whereNull('result')->whereNull('commander_scores')->whereNull('timer_ends_at')
                    ->update(['timer_ends_at' => now()->addMinutes($data['minutes']), 'life_revision' => $table->life_revision + 1]);
                if (! $saved) {
                    continue;
                }
                $state = [...$life->snapshot($tournament, $table->fresh()), 'operation' => 'timer'];
                rescue(fn () => event(new LifeChanged($tournament->invite_token, $state)), report: true);
                rescue(fn () => event(new BoardChanged), report: true);

                return response()->json($state)->header('Cache-Control', 'no-store');
            }
            if ($data['action'] === 'finish') {
                $alive = collect($state['players'])->filter(fn ($player) => $player['life'] > 0
                    && ($tournament->format !== 'commander' || max([0, ...array_values($player['damage'])]) < 21));
                abort_unless($alive->count() === 1 && $alive->first()['id'] === (int) $data['winner'], 409, 'Der Gewinner hat sich inzwischen geändert.');
                $operation = 'finish';
                if ($tournament->format === 'commander') {
                    $result = ['commander_scores' => json_encode($players->mapWithKeys(fn ($player) => [$player->id => $player->id === (int) $data['winner'] ? 3 : 0])->all(), JSON_THROW_ON_ERROR)];
                } else {
                    $wins = $players->mapWithKeys(fn ($player) => [$player->id => (int) ($table->game_wins[$player->id] ?? 0)])->all();
                    $wins[(int) $data['winner']]++;
                    $a = $wins[$table->player_a_id];
                    $b = $wins[$table->player_b_id];
                    $result = ['game_wins' => json_encode($wins, JSON_THROW_ON_ERROR)];
                    if ($a === 2 || $b === 2) {
                        $result['result'] = RoundMatchResult::from($a.'-'.$b)->value;
                    } else {
                        $result['life_state'] = null;
                        $operation = 'game-win';
                    }
                }
                abort_unless($table->newQuery()->whereKey($table->id)->where('life_revision', $table->life_revision)
                    ->whereNull('result')->whereNull('commander_scores')
                    ->update([...$result, 'life_revision' => $table->life_revision + 1]), 409);
                $state = [...$life->snapshot($tournament, $table->fresh()), 'operation' => $operation];
                rescue(fn () => event(new LifeChanged($tournament->invite_token, $state)), report: true);
                rescue(fn () => event(new BoardChanged), report: true);

                return response()->json($state)->header('Cache-Control', 'no-store');
            }
            $values = collect($state['players'])->mapWithKeys(fn ($player) => [$player['id'] => ['life' => $player['life'], 'damage' => $player['damage']]])->all();
            if ($data['action'] === 'reset') {
                $values = null;
            } elseif ($data['action'] === 'set-life') {
                $values[$data['player']]['life'] = (int) $data['value'];
            } elseif ($data['action'] === 'life') {
                $values[$data['player']]['life'] = max(-999, min(9999, $values[$data['player']]['life'] + $data['delta']));
            } else {
                $key = (string) $data['source'];
                $before = $values[$data['player']]['damage'][$key] ?? 0;
                $after = max(0, min(999, $before + $data['delta']));
                $values[$data['player']]['damage'][$key] = $after;
                $values[$data['player']]['life'] -= $after - $before;
            }
            $saved = $table->newQuery()->whereKey($table->id)->where('life_revision', $table->life_revision)
                ->whereNull('result')->whereNull('commander_scores')->update([
                    'life_state' => $values === null ? null : json_encode($values, JSON_THROW_ON_ERROR),
                    'life_revision' => $table->life_revision + 1,
                ]);
            if ($saved) {
                $state = [...$life->snapshot($tournament, $table->fresh()), 'operation' => $data['action']];
                rescue(fn () => event(new LifeChanged($tournament->invite_token, $state)), report: true);

                return response()->json($state)->header('Cache-Control', 'no-store');
            }
        }
        abort(409, 'Viele gleichzeitige Änderungen. Bitte erneut versuchen.');
    }
}

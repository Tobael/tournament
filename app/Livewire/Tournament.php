<?php

namespace App\Livewire;

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Models\RoundMatch;
use App\Services\CommanderTournamentService;
use App\Services\SwissTournamentService;
use App\Services\TvMusic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Tournament extends Component
{
    public \App\Models\Tournament $tournament;

    public string $tvYoutube = '';

    public function controlTvMusic(bool $stop = false): void
    {
        $this->tournament->refresh();
        abort_unless($this->tournament->participant() && \App\Models\Tournament::forTv()?->id === $this->tournament->id, 403);
        $url = null;
        if (! $stop) {
            $this->validate(['tvYoutube' => ['required', 'string', 'max:500']]);
            $url = TvMusic::embed(trim($this->tvYoutube));
            if (! $url) {
                $this->addError('tvYoutube', 'Bitte einen gültigen YouTube-Link zu einem Video oder einer Playlist einfügen.');

                return;
            }
        }
        $this->tournament->forceFill(['tv_youtube_url' => $url, 'tv_music_revision' => (string) Str::uuid()])->save();
        session()->flash('tv-music-status', $stop ? 'Musik auf dem TV gestoppt.' : 'An den TV gesendet. Die Wiedergabe startet innerhalb von 10 Sekunden.');
    }

    public bool $hideNextRoundButton = false;

    public function mount(\App\Models\Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    public function refresh(): void
    {
        $this->tournament->refresh();
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        $this->tournament->loadMissing(['group', 'users.user', 'matches', 'rounds.matches.playerA.user', 'rounds.matches.playerB.user', 'rounds.matches.players.user']);

        return view('livewire.tournament')->title($this->tournament->name.' · Spielrunde');
    }

    public function openFinishModal(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->modal('finish-tournament')->show();
    }

    public function finishTournament(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->tournament->update(['status' => Status::CLOSED]);
        $this->modal('finish-tournament')->close();
    }

    public function startNextRound(SwissTournamentService $swissTournamentService, CommanderTournamentService $commanderService): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        DB::transaction(function () use ($swissTournamentService, $commanderService) {
            $this->tournament = $this->tournament->newQuery()->lockForUpdate()->findOrFail($this->tournament->id);
            abort_if($this->tournament->status === Status::CLOSED, 422);
            abort_if($this->tournament->currentRound() && ! $this->tournament->currentRound()->isCompleted(), 422);
            abort_if($this->tournament->users()->count() < 2, 422);
            if ($this->tournament->format === 'commander') {
                abort_if($this->tournament->rounds()->count() >= $this->tournament->round_limit, 422);
            }
            $service = $this->tournament->format === 'commander' ? $commanderService : $swissTournamentService;
            if (! $service->generateNextRound($this->tournament)) {
                $this->modal('no-more-matches')->show();
                $this->hideNextRoundButton = true;

                return;
            }
            $this->tournament->update(['status' => Status::IN_PROGRESS]);
            $this->tournament->refresh();
        });
    }

    private function reportingTable(int $matchId): RoundMatch
    {
        $this->tournament->refresh();
        abort_unless($this->tournament->format === 'commander' && $this->tournament->status === Status::IN_PROGRESS, 422);
        $match = $this->tournament->currentRound()?->matches()->with('players')->find($matchId);
        abort_unless($match, 404);
        $participant = $this->tournament->participant();
        abort_unless(auth()->user()?->is_admin || ($participant && $match->players->contains('id', $participant->id)), 403);

        return $match;
    }

    private function saveCommanderScores(RoundMatch $match, array $scores): void
    {
        abort_unless($match->newQuery()->whereKey($match->id)->whereNull('commander_scores')->whereNull('result')
            ->update(['commander_scores' => json_encode($scores, JSON_THROW_ON_ERROR)]), 409);
        $this->tournament->refresh();
    }

    public function reportCommanderWinner(int $matchId, int $winnerId): void
    {
        $match = $this->reportingTable($matchId);
        abort_unless($match->players->contains('id', $winnerId), 422);
        $this->saveCommanderScores($match, $match->players->mapWithKeys(fn ($player) => [$player->id => $player->id === $winnerId ? 3 : 0])->all());
    }

    public function reportCommanderTimeout(int $matchId, array $survivors): void
    {
        $match = $this->reportingTable($matchId);
        abort_unless($match->timer_ends_at?->isPast(), 422);
        validator(['survivors' => $survivors], [
            'survivors' => ['required', 'array', 'min:1', 'max:5'],
            'survivors.*' => ['required', 'integer', 'distinct', Rule::in($match->players->modelKeys())],
        ])->validate();
        $survivors = array_map('intval', $survivors);
        $this->saveCommanderScores($match, $match->players->mapWithKeys(fn ($player) => [$player->id => in_array($player->id, $survivors, true) ? 1 : 0])->all());
    }

    public function reportCommanderDraw(int $matchId): void
    {
        $match = $this->reportingTable($matchId);
        $this->saveCommanderScores($match, $match->players->mapWithKeys(fn ($player) => [$player->id => 1])->all());
    }

    public function startMatchTimer(int $matchId, int $minutes = 50): void
    {
        $this->tournament->refresh();
        abort_unless($this->tournament->status === Status::IN_PROGRESS, 422);
        abort_unless(in_array($minutes, [25, 50, 60, 90], true), 422);
        $match = $this->tournament->currentRound()?->matches()->whereKey($matchId)->first();
        abort_unless($match && $match->player_b_id, 404);
        // A shared deadline can only be set once, including concurrent starts.
        $match->newQuery()->whereKey($match->id)->whereNull('result')->whereNull('commander_scores')->whereNull('timer_ends_at')
            ->update(['timer_ends_at' => now()->addMinutes($minutes)]);
        $this->tournament->refresh();
    }

    public function updateCurrentMatchForUser(RoundMatchResult $result): void
    {
        $this->tournament->refresh();
        abort_unless($this->tournament->status === Status::IN_PROGRESS, 422);
        abort_if($this->tournament->format === 'commander', 422);
        $match = $this->tournament->currentRound()?->getMatchForUser();
        abort_unless($match && $match->player_b_id, 403);
        // Only the first submission wins, even if both players submit together.
        abort_unless($match->newQuery()->whereKey($match->id)->whereNull('result')->update([
            'result' => $result->value,
        ]), 409);
        $this->tournament->refresh();

    }
}

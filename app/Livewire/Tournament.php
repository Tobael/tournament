<?php

namespace App\Livewire;

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Services\SwissTournamentService;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Tournament extends Component
{
    public \App\Models\Tournament $tournament;

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
        $this->tournament->loadMissing(['group', 'users.user', 'matches', 'rounds.matches.playerA.user', 'rounds.matches.playerB.user']);

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

    public function startNextRound(SwissTournamentService $swissTournamentService): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->tournament->refresh();
        abort_if($this->tournament->status === Status::CLOSED, 422);
        abort_if($this->tournament->currentRound() && ! $this->tournament->currentRound()->isCompleted(), 422);
        abort_if($this->tournament->users()->count() < 2, 422);

        if (! $swissTournamentService->generateNextRound($this->tournament)) {
            $this->modal('no-more-matches')->show();
            $this->hideNextRoundButton = true;

            return;
        }

        $this->tournament->update(['status' => Status::IN_PROGRESS]);
        $this->tournament->refresh();

    }

    public function updateCurrentMatchForUser(RoundMatchResult $result): void
    {
        $this->tournament->refresh();
        abort_unless($this->tournament->status === Status::IN_PROGRESS, 422);
        $match = $this->tournament->currentRound()?->getMatchForUser();
        abort_unless($match && $match->player_b_id, 403);
        // Only the first submission wins, even if both players submit together.
        abort_unless($match->newQuery()->whereKey($match->id)->whereNull('result')->update([
            'result' => $result->value,
        ]), 409);
        $this->tournament->refresh();

    }
}

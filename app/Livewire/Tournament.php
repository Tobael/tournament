<?php

namespace App\Livewire;

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Events\TournamentUpdated;
use App\Services\SwissTournamentService;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Tournament extends Component
{
    public \App\Models\Tournament $tournament;

    public string $tab;

    public function mount(\App\Models\Tournament $tournament)
    {
        $this->tournament = $tournament;
        $this->tab = $tournament->getLastRound()?->id ?? 'standings';
    }

    protected function getListeners(): array
    {
        return [
            "echo-private:tournament.{$this->tournament->id},TournamentUpdated" => 'refresh',
        ];
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.tournament');
    }

    public function openFinishModal(): void
    {
        $this->modal('finish-tournament')->show();
    }

    public function finishTournament(): void
    {
        $this->tournament->update(['status' => Status::CLOSED]);
        event(new TournamentUpdated($this->tournament->id));
        $this->modal('finish-tournament')->close();
    }

    public function startNextRound(SwissTournamentService $swissTournamentService): void
    {
        if ($this->tournament->status === Status::OPEN) {
            $this->tournament->update(['status' => Status::IN_PROGRESS]);
        }

        $swissTournamentService->generateNextRound($this->tournament);

        $this->tab = $this->tournament->getLastRound()->id;

        event(new TournamentUpdated($this->tournament->id));
    }

    public function updateCurrentMatchForUser(RoundMatchResult $result): void
    {
        $match = $this->tournament->rounds->sortByDesc('round')->first()->getCurrentMatchForUser();
        $match->update([
            'result' => $result,
        ]);

        event(new TournamentUpdated($this->tournament->id));
    }
}

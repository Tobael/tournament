<?php

namespace App\Livewire;

use App\Enums\RoundMatchResult;
use App\Enums\Status;
use App\Events\TournamentUpdated;
use App\Models\TournamentUser;
use App\SwissTournamentHandler;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Tournament extends Component
{
    public \App\Models\Tournament $tournament;

    public string $tab = "round";

    public function mount(\App\Models\Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    protected function getListeners(): array
    {
        return [
            "echo-private:tournament.{$this->tournament->id},TournamentUpdated" => 'refresh',
        ];
    }

    public function deleteParticipant(TournamentUser $tournamentUser)
    {
        // TODO
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return match ($this->tournament->status) {
            Status::OPEN, Status::CLOSED => view('livewire.tournament'),
            Status::IN_PROGRESS => view('livewire.tournament', [
                'matches' => $this->tournament->matches,
                'rounds' => $this->tournament->rounds,
                'currentMatch' => $this->tournament->getCurrentMatchFor(auth()->user()),
            ]),
        };
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

    public function startNextRound(): void
    {
        if ($this->tournament->status === Status::OPEN) {
            $this->tournament->update(['status' => Status::IN_PROGRESS]);
        }
        SwissTournamentHandler::create($this->tournament)->generateNextRound();
        event(new TournamentUpdated($this->tournament->id));
    }

    public function updateCurrentMatchForUser(RoundMatchResult $result): void
    {
        $match = $this->tournament->getCurrentMatchFor(auth()->user());
        $match->update([
            'result' => $result,
        ]);

        event(new TournamentUpdated($this->tournament->id));
    }
}

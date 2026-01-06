<?php

namespace App\Livewire;

use App\Enums\Status;
use App\Models\TournamentUser;
use App\SwissTournamentHandler;
use Livewire\Component;

class Tournament extends Component
{
    public \App\Models\Tournament $tournament;

    public function mount(\App\Models\Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    public function deleteParticipant(TournamentUser $tournamentUser)
    {
        // TODO
    }

    public function render()
    {
        return match ($this->tournament->status) {
            Status::OPEN, Status::CLOSED => view('livewire.tournament'),
            Status::IN_PROGRESS => view('livewire.tournament', ['matches' => $this->tournament->matches, 'rounds' => $this->tournament->rounds]),
        };
    }

    public function openFinishModal(): void
    {
        $this->modal('finish-tournament')->show();
    }

    public function finishTournament(): void
    {
        $this->tournament->update(['status' => Status::CLOSED]);
        $this->modal('finish-tournament')->close();
    }

    public function startNextRound(): void
    {
        SwissTournamentHandler::create($this->tournament)->generateNextRound();
    }
}

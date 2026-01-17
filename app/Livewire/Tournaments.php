<?php

namespace App\Livewire;

use App\Enums\Status;
use App\Models\Group;
use App\Models\Tournament;
use App\Models\TournamentUser;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Tournaments extends Component
{
    #[Validate('required|string|max:255')]
    public string $name;
    #[Validate('required|int')]
    public int $groupId;
    public ?string $deckname = null;
    public string $sortDirection = 'desc';
    public string $sortBy = 'created_at';

    public ?Tournament $selectedTournament = null;
    public ?int $selectedTournamentId = null;


    public function sort($column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        return view('livewire.tournaments', ['groups' => Group::all()])->layout('components.layouts.app', ["title" => "Tournaments"]);
    }

    public function openCreateModal(): void
    {
        $this->reset(['name']);
        $this->modal('create-tournament')->show();
    }

    public function createTournament(): void
    {
        $this->validate();

        Tournament::create([
            'name' => $this->name,
            'status' => Status::OPEN,
            'group_id' => $this->groupId
        ]);
        $this->modal('create-tournament')->close();
        $this->reset(['name', 'groupId']);
    }

    public function deleteTournament(): void
    {
        $this->selectedTournament->delete();
        $this->modal('delete-tournament')->close();
    }

    public function editTournament(): void
    {
        $this->selectedTournament->update([
            'name' => $this->name,
            'group_id' => $this->groupId,
        ]);

        $this->modal('edit-tournament')->close();
    }

    public function participateTournament(): void
    {
        if ($this->selectedTournament->status !== Status::OPEN) {
            return;
        }

        TournamentUser::firstOrCreate(
            [
                'user_id' => auth()->id(),
                'tournament_id' => $this->selectedTournament->id,
            ],
            [
                'deckname' => $this->deckname,
            ]
        );

        $this->modal('participate-tournament')->close();
    }

    #[Computed]
    public function tournaments()
    {
        return Tournament::query()->tap(fn($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)->paginate(10);
    }

    public function openParticipateModal(Tournament $tournament): void
    {
        $this->selectedTournament = $tournament;
        $this->reset('deckname');
        $this->modal('participate-tournament')->show();
    }

    public function openEditModal(Tournament $tournament): void
    {
        $this->selectedTournament = $tournament;
        $this->name = $this->selectedTournament->name;
        $this->groupId = $this->selectedTournament->group_id;

        $this->modal('edit-tournament')->show();
    }

    public function openDeleteModal(Tournament $tournament): void
    {
        $this->selectedTournament = $tournament;
        $this->modal('delete-tournament')->show();
    }
}

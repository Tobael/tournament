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

    public function openDeleteModal(Tournament $tournament): void
    {
        $this->modal("delete-tournament-$tournament->id")->show();
    }

    public function openParticipateModal(Tournament $tournament): void
    {
        $this->modal("participate-tournament-$tournament->id")->show();
    }

    public function deleteTournament(Tournament $tournament): void
    {
        $tournament->delete();
        $this->modal("delete-tournament-$tournament->id")->close();
    }

    public function openEditModal(Tournament $tournament): void
    {
        $this->name = $tournament->name;
        $this->groupId = $tournament->group->id;
        $this->modal("edit-tournament-$tournament->id")->show();
    }

    public function editTournament(Tournament $tournament): void
    {
        $tournament->update([
            'name' => $this->name,
            'group_id' => $this->groupId
        ]);
        $this->modal("edit-tournament-$tournament->id")->close();
        $this->reset(['name', 'groupId']);
    }

    public function participateTournament(Tournament $tournament): void
    {
        TournamentUser::create(['user_id' => auth()->user()->id, 'tournament_id' => $tournament->id, 'deckname' => $this->deckname]);
        $this->modal("participate-tournament-$tournament->id")->close();
    }

    #[Computed]
    public function tournaments()
    {
        return Tournament::query()->tap(fn($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)->paginate(10);
    }
}

<?php

namespace App\Livewire;

use App\Enums\Status;
use App\Models\Group;
use App\Models\Tournament;
use App\Services\MagicSetCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class Tournaments extends Component
{
    use WithPagination;

    #[Validate('required|string|max:255')]
    public string $name;

    #[Validate('required|in:sealed,constructed,commander')]
    public string $format = 'sealed';

    public string $setCode = '';

    #[Validate('required|in:balanced,small,large')]
    public string $tablePreference = 'balanced';

    #[Validate('required|integer|min:1|max:10')]
    public int $roundLimit = 3;

    #[Locked]
    public array $sets = [];

    private function setDetails(): array
    {
        if ($this->format !== 'sealed' || $this->setCode === '') {
            return ['format' => $this->format, 'set_code' => null, 'set_name' => null];
        }
        $sets = app(MagicSetCatalog::class)->sets();
        // Preserve a historical selection even if the provider removes the set.
        if ($this->selectedTournament?->set_code) {
            $sets[] = ['code' => $this->selectedTournament->set_code, 'name' => $this->selectedTournament->set_name];
        }
        $this->validate(['setCode' => ['required', Rule::in(array_column($sets, 'code'))]]);
        $set = collect($sets)->firstWhere('code', $this->setCode);

        return ['format' => $this->format, 'set_code' => $set['code'], 'set_name' => $set['name']];
    }

    #[Validate('required|integer|exists:groups,id')]
    public int $groupId;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public string $sortDirection = 'desc';

    public string $sortBy = 'created_at';

    public ?Tournament $selectedTournament = null;

    public ?int $selectedTournamentId = null;

    public function mount(): void
    {
        $this->groupId = (Group::first() ?? Group::create(['name' => 'Spielrunde']))->id;
    }

    public function sort($column): void
    {
        abort_unless(in_array($column, ['name', 'created_at', 'status'], true), 422);
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Layout('components.layouts.app', ['title' => 'Turniere'])]
    public function render()
    {
        return view('livewire.tournaments', [
            'groups' => Group::all(),
        ]);
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->reset(['name', 'format', 'setCode', 'selectedTournament', 'tablePreference', 'roundLimit']);
        $this->sets = app(MagicSetCatalog::class)->sets();
        $this->modal('create-tournament')->show();
    }

    public function createTournament(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->validate();

        Tournament::create([
            'name' => $this->name,
            ...$this->setDetails(),
            'table_preference' => $this->tablePreference,
            'round_limit' => $this->roundLimit,
            'status' => Status::OPEN,
            'group_id' => $this->groupId,
        ]);

        $this->modal('create-tournament')->close();
        $this->reset(['name', 'format', 'setCode', 'selectedTournament', 'tablePreference', 'roundLimit']);
    }

    public function deleteTournament(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        if (auth()->user()->is_admin) {
            $this->selectedTournament->delete();
        }
        $this->modal('delete-tournament')->close();
    }

    public function editTournament(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->validate();
        $this->selectedTournament->refresh();
        if ($this->selectedTournament->rounds()->exists()) {
            $this->validate([
                'format' => [Rule::in([$this->selectedTournament->format])],
                'tablePreference' => [Rule::in([$this->selectedTournament->table_preference])],
                'roundLimit' => [Rule::in([$this->selectedTournament->round_limit])],
            ], ['in' => 'Format und Rundeneinstellungen können nach dem Start nicht geändert werden.']);
        }
        $this->selectedTournament->update([
            ...$this->setDetails(),
            'table_preference' => $this->tablePreference,
            'round_limit' => $this->roundLimit,
            'name' => $this->name,
            'group_id' => $this->groupId,
        ]);

        $this->modal('edit-tournament')->close();
    }

    #[Computed]
    public function tournaments()
    {
        abort_unless(in_array($this->sortBy, ['name', 'created_at', 'status'], true) && in_array($this->sortDirection, ['asc', 'desc'], true), 422);

        abort_unless(in_array($this->status, ['', 'OPEN', 'IN_PROGRESS', 'CLOSED'], true) && mb_strlen($this->search) <= 100, 422);

        return Tournament::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->with('group')->withCount('users')->withExists(['users as joined' => fn ($query) => $query->where('user_id', auth()->id())])->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)->paginate(10);
    }

    public function openEditModal(Tournament $tournament): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->selectedTournament = $tournament;
        $this->name = $this->selectedTournament->name;
        $this->groupId = $this->selectedTournament->group_id;
        $this->format = $tournament->format;
        $this->tablePreference = $tournament->table_preference;
        $this->roundLimit = $tournament->round_limit;
        $this->setCode = $tournament->set_code ?? '';
        $this->sets = app(MagicSetCatalog::class)->sets();
        if ($tournament->set_code && ! collect($this->sets)->contains('code', $tournament->set_code)) {
            $this->sets[] = ['code' => $tournament->set_code, 'name' => $tournament->set_name];
        }

        $this->modal('edit-tournament')->show();
    }

    public function openDeleteModal(Tournament $tournament): void
    {
        abort_unless(auth()->user()?->is_admin, 403);

        $this->selectedTournament = $tournament;
        $this->modal('delete-tournament')->show();
    }
}

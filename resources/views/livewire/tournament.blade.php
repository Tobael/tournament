@php use App\Enums\Status; @endphp
<div>
    @if($tournament->status === Status::OPEN)
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Deck Name</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($tournament->users as $tournamentUser)
                    <flux:table.row :key="$tournamentUser->id">
                        <flux:table.cell>{{ $tournamentUser->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $tournamentUser->deckname }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @elseif($tournament->status === Status::IN_PROGRESS)
        <flux:button :variant="$tournament->currentRound()->isCompleted() ? 'success' : 'danger'"
                     wire:click="openFinishModal">Turnier beenden
        </flux:button>
        @if ($tournament->currentRound()->isCompleted())
            <flux:button wire:click="startNextRound">Nächste Runde starten</flux:button>
        @endif
        @foreach($matches as $match)
            {{ $match }}
        @endforeach
    @elseif($tournament->status === Status::CLOSED)
        Tournament {{ $tournament->id }} is closed
    @endif


    <flux:modal name="finish-tournament">
        <flux:heading size="lg">Turnier beenden</flux:heading>

        <div class="mb-5">
            Möchtest du das Turnier
            <strong>"{{ $tournament->name }}"</strong>
            wirklich beenden?
        </div>
        @if(!$tournament->currentRound()->isCompleted())
            <div class="mb-5">
                <strong>Die aktuelle Turnierrunde ist noch nicht ausgespielt!</strong>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <flux:modal.close>
                <flux:button variant="ghost">Abbrechen</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" wire:click="finishTournament">
                Ja
            </flux:button>
        </div>
    </flux:modal>
</div>

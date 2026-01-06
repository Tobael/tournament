@php
    use App\Enums\Status;
    use App\Enums\RoundMatchResult;
@endphp
<div>
    @if($tournament->status === Status::OPEN)
        <flux:button wire:click="startNextRound">Turnier starten</flux:button>
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
        <flux:button.group>
            <flux:button :variant="$tournament->currentRound()->isCompleted() ? 'primary' : 'danger'"
                        wire:click="openFinishModal">Turnier beenden
            </flux:button>
            @if ($tournament->currentRound()->isCompleted())
                <flux:button wire:click="startNextRound">Nächste Runde starten</flux:button>
            @endif
        </flux:button.group>

        <flux:heading size="xl">Runde {{ $this->tournament->currentRound()->round }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Player A</flux:table.column>
                <flux:table.column>Result</flux:table.column>
                <flux:table.column>Player B</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach($matches as $match)
                    <flux:table.row :key="$match->id">
                        <flux:table.cell>{{ $match->playerA->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $match->result }}</flux:table.cell>
                        <flux:table.cell>{{ $match->playerB->user->name ?? 'BYE' }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        @if(!$currentMatch->result)
            <flux:card class="flex flex-col gap-4 align-items-center">
                <flux:heading size="lg">{{ $currentMatch->playerA->user->name }} - {{ $currentMatch->playerB->user->name }}</flux:heading>

                <flux:button.group class="w-full">
                    <flux:button class="w-full" wire:click="updateCurrentMatchForUser('{{ RoundMatchResult::TWOZERO }}')">{{ RoundMatchResult::TWOZERO }}</flux:button>
                    <flux:button class="w-full" wire:click="updateCurrentMatchForUser('{{ RoundMatchResult::ZEROTWO }}')">{{ RoundMatchResult::ZEROTWO }}</flux:button>
                </flux:button.group>
                <flux:button.group>
                    <flux:button class="w-full" wire:click="updateCurrentMatchForUser('{{ RoundMatchResult::TWOONE }}')">{{ RoundMatchResult::TWOONE }}</flux:button>
                    <flux:button class="w-full" wire:click="updateCurrentMatchForUser('{{ RoundMatchResult::ONETWO }}')">{{ RoundMatchResult::ONETWO }}</flux:button>
                </flux:button.group>
                <flux:button wire:click="updateCurrentMatchForUser('{{ RoundMatchResult::DRAW }}')">{{ RoundMatchResult::DRAW }}</flux:button>
            </flux:card>
        @endif
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

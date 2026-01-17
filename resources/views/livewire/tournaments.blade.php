<div>
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Turniere') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Turnier erstellen') }}
        </flux:button>
    </div>
    <flux:table :paginate="$this->tournaments">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                               wire:click="sort('name')">Name
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection"
                               wire:click="sort('created_at')">Datum
            </flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'status'" :direction="$sortDirection"
                               wire:click="sort('status')">Status
            </flux:table.column>
            <flux:table.column></flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->tournaments as $tournament)
                <flux:table.row :key="$tournament->id">
                    <flux:table.cell class="flex items-center gap-3" wire:navigate :href="route('tournament', $tournament)">
                        {{ $tournament->name }}
                    </flux:table.cell>

                    <flux:table.cell wire:navigate :href="route('tournament', $tournament)"
                        class="whitespace-nowrap">{{ $tournament->created_at->format('d.m.Y H:i:s') }}</flux:table.cell>

                    <flux:table.cell wire:navigate :href="route('tournament', $tournament)">
                        <flux:icon name="{{$tournament->status->toIcon()}}"></flux:icon>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if($tournament->status == App\Enums\Status::OPEN && $tournament->users()->where('user_id', auth()->id())->exists())
                            <flux:button icon="arrow-right-end-on-rectangle" variant="ghost" size="sm"
                                         class="cursor-pointer"
                                         wire:click="openParticipateModal({{$tournament->id}})"
                                         inset="top bottom"></flux:button>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button icon="pencil" variant="ghost" size="sm" class="cursor-pointer"
                                     wire:click.stop="openEditModal({{$tournament->id}})"
                                     inset="top bottom"></flux:button>
                        @if(auth()->user()->is_admin)
                            <flux:button icon="trash" variant="ghost" size="sm" class="cursor-pointer"
                                         wire:click.stop="openDeleteModal({{$tournament->id}})"
                                         inset="top bottom"></flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <!-- Modals -->
    <flux:modal name="participate-tournament">
        @if($selectedTournament)
            <flux:heading size="lg">
                An Turnier teilnehmen
            </flux:heading>

            <p>
                Möchtest du dem Turnier
                <strong>"{{ $selectedTournament->name }}"</strong>
                beitreten?
            </p>

            <form wire:submit="participateTournament" class="space-y-6">
                <flux:input wire:model="deckname" label="Deck Name"/>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary" color="green">Ja</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="edit-tournament">
        @if($selectedTournament)
            <flux:heading size="lg">
                Turnier bearbeiten
            </flux:heading>

            <form wire:submit="editTournament" class="space-y-6">
                <flux:input wire:model="name" label="Name"/>

                <flux:select wire:model="groupId" placeholder="Gruppe auswählen...">
                    @foreach($groups as $group)
                        <flux:select.option value="{{ $group->id }}">
                            {{ $group->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">Abbrechen</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary">Speichern</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="delete-tournament">
        @if($selectedTournament)
            <flux:heading size="lg">Turnier löschen</flux:heading>

            <div class="mb-5">
                Möchtest du das Turnier
                <strong>"{{ $selectedTournament->name }}"</strong>
                wirklich löschen?
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteTournament">
                    Ja
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="create-tournament">
        <flux:heading size="lg">
            {{ __('Turnier hinzufügen') }}
        </flux:heading>

        <form class="space-y-6">
            <flux:input
                wire:model="name"
                label="Name"
            />

            <flux:select
                wire:model="groupId"
                placeholder="Gruppe auswählen..."
            >
                @foreach ($groups as $group)
                    <flux:select.option value="{{ $group->id }}">
                        {{ $group->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-3 pt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">
                        Abbrechen
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="primary"
                    wire:click="createTournament"
                >
                    Ok
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>

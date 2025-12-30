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
                    <flux:table.cell class="flex items-center gap-3">
                        {{ $tournament->name }}
                    </flux:table.cell>

                    <flux:table.cell class="whitespace-nowrap">{{ $tournament->created_at->format('d.m.Y H:m:s') }}</flux:table.cell>

                    <flux:table.cell>
                        <flux:icon name="{{$tournament->status->toIcon()}}"></flux:icon>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button icon="arrow-right-end-on-rectangle" variant="ghost" size="sm"
                                     class="cursor-pointer"
                                     wire:click="openParticipateModal({{$tournament->id}})"
                                     inset="top bottom"></flux:button>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:button icon="pencil" variant="ghost" size="sm" class="cursor-pointer"
                                     wire:click="openEditModal({{$tournament->id}})"
                                     inset="top bottom"></flux:button>
                        <flux:button icon="trash" variant="ghost" size="sm" class="cursor-pointer"
                                     wire:click="openDeleteModal({{$tournament->id}})"
                                     inset="top bottom"></flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="create-tournament">
        <div class="flex items-center justify-between mb-6">
            <flux:heading size="lg">{{ __('Turnier hinzufügen') }}</flux:heading>
        </div>

        <form wire:submit="createTournament" class="space-y-6">
            <flux:input
                wire:model="name"
                :label="__('Name')"
            />

            <flux:select wire:model="groupId" variant="listbox" placeholder="Gruppe auswählen...">
                @foreach ($groups as $group)
                    <flux:select.option
                        value="{{$group->id}}">{{ $group->name }}</flux:select.option>
                @endforeach
            </flux:select>


            <div class="flex justify-end gap-3 pt-4">
                <flux:modal.close>
                    <flux:button
                        type="button"
                        variant="ghost"
                    >
                        {{ __('Abbrechen') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="submit"
                    variant="primary"
                >
                    {{ __('Ok') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    @foreach($this->tournaments as $tournament)
        <flux:modal name="participate-tournament-{{$tournament->id}}">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">{{ __('An Turnier teilnehmen') }}</flux:heading>
            </div>

            <div>
                Möchstest du dem Turnier "{{$tournament->name}}" beitreten?
            </div>

            <form wire:submit="participateTournament({{$tournament->id}})" class="space-y-6">
                <flux:input
                    wire:model="deckname"
                    :label="__('Deck Name')"
                />

                <div class="flex justify-end gap-3 pt-4">
                    <flux:modal.close>
                        <flux:button
                            type="button"
                            variant="ghost"
                        >
                            {{ __('Abbrechen') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button
                        type="submit"
                        variant="primary"
                        color="green"
                    >
                        {{ __('Ja') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
        <flux:modal name="delete-tournament-{{$tournament->id}}">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">{{ __('Turnier löschen') }}</flux:heading>
            </div>

            <div>
                Möchstest du das Turnier "{{$tournament->name}}" wirklich löschen?
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <flux:modal.close>
                    <flux:button
                        type="button"
                        variant="ghost"
                    >
                        {{ __('Abbrechen') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="submit"
                    variant="danger"
                    wire:click="deleteTournament({{$tournament->id}})"
                >
                    {{ __('Ja') }}
                </flux:button>
            </div>
        </flux:modal>
        <flux:modal name="edit-tournament-{{$tournament->id}}">
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">{{ __('Turnier "'.$tournament->name.'" bearbeiten') }}</flux:heading>
            </div>

            <form wire:submit="editTournament({{$tournament->id}})" class="space-y-6">
                <flux:input
                    wire:model="name"
                    :label="__('Name')"
                />

                <flux:select wire:model="groupId" variant="listbox" placeholder="Gruppe auswählen...">
                    @foreach ($groups as $group)
                        <flux:select.option
                            :selected="$groupId === $group->id"
                            value="{{$group->id}}">{{ $group->name }}</flux:select.option>
                    @endforeach
                </flux:select>


                <div class="flex justify-end gap-3 pt-4">
                    <flux:modal.close>
                        <flux:button
                            type="button"
                            variant="ghost"
                        >
                            {{ __('Abbrechen') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button
                        type="submit"
                        variant="primary"
                    >
                        {{ __('Ok') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endforeach
</div>

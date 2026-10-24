<div>
    <section class="page-intro">
        <p class="eyebrow">DEIN NÄCHSTES MATCH WARTET</p>
        <div class="intro-row"><div><h1>Zusammen an den Tisch.</h1><p class="muted">Turnier öffnen, mitspielen, Ergebnisse eintragen.</p></div>
        @if(auth()->user()->is_admin)
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">Turnier erstellen</flux:button>
        @endif</div>
    </section>
    <div class="section-heading"><h2>Turniere</h2><label>Sortieren <select wire:change="sort($event.target.value)" aria-label="Turniere sortieren"><option value="scheduled_on">Spieltermin</option><option value="created_at">Erstellt</option><option value="name">Name</option><option value="status">Status</option></select></label></div>
    <div class="event-filters">
        <flux:input wire:model.live.debounce.300ms="search" type="search" label="Turnier suchen" placeholder="Name des Turniers" maxlength="100" />
        <flux:select wire:model.live="status" label="Status"><option value="">Alle Turniere</option><option value="OPEN">Anmeldung offen</option><option value="IN_PROGRESS">Wird gespielt</option><option value="CLOSED">Beendet</option></flux:select>
    </div>
    <div class="tournament-grid" wire:loading.class="opacity-60">
        @forelse ($this->tournaments as $tournament)
            <article class="event-card">
                <div class="card-top"><span class="status-pill {{ strtolower($tournament->status->name) }}">{{ match($tournament->status) { App\Enums\Status::OPEN => 'Anmeldung offen', App\Enums\Status::IN_PROGRESS => 'Wird gespielt', default => 'Beendet' } }}</span><span class="muted">{{ $tournament->scheduled_on?->format('d.m.Y') ?? 'Termin offen' }}</span></div>
                @if($tournament->format === 'sealed' && $tournament->set_code)
                    <x-set-visual :code="$tournament->set_code" :name="$tournament->set_name" :context="'card-'.$tournament->id" />
                @endif
                <a class="event-title" href="{{ route('tournament', $tournament) }}" wire:navigate>{{ $tournament->name }}</a>
                <p class="muted">{{ match($tournament->format) { 'sealed' => 'Sealed', 'commander' => 'Commander', default => 'Constructed' } }}{{ $tournament->set_name ? ' · '.$tournament->set_name : '' }}</p>
                <p class="muted">{{ $tournament->group->name }} · {{ $tournament->users_count }} Teilnehmende</p>
                <div class="card-actions">
                    <a class="arena-button" href="{{ route('tournament', $tournament) }}" wire:navigate>Turnier öffnen <span aria-hidden="true">↗</span></a>
                    @if($tournament->status === App\Enums\Status::OPEN && !$tournament->joined)
                        <a class="utility-button" href="{{ route('tournament', $tournament) }}#join" wire:navigate>Mitspielen</a>
                    @endif
                </div>
                @if(auth()->user()->is_admin)
                    <div class="admin-actions"><flux:button variant="ghost" wire:click="openEditModal({{ $tournament->id }})">Bearbeiten</flux:button><flux:button variant="ghost" wire:click="openDeleteModal({{ $tournament->id }})">Löschen</flux:button></div>
                @endif
            </article>
        @empty
            <div class="empty-state"><h2>{{ $search || $status ? 'Kein passendes Turnier.' : 'Platz für die nächste Spielrunde.' }}</h2><p>{{ $search || $status ? 'Versuche einen anderen Namen oder Status.' : 'Hier erscheinen eure Turniere, sobald die Organisation eines erstellt.' }}</p></div>
        @endforelse
    </div>
    <div class="pagination">{{ $this->tournaments->links() }}</div>
    <!-- Modals -->
    <flux:modal name="edit-tournament">
        @if($selectedTournament)
            <flux:heading size="lg">
                Turnier bearbeiten
            </flux:heading>

            <form wire:submit="editTournament" class="space-y-6">
                <flux:input wire:model="name" label="Name"/>
                <flux:input wire:model="scheduledDate" type="date" label="Datum des Spielabends (optional)" />
                <x-tournament-format-fields :format="$format" :sets="$sets" :table-preference="$tablePreference" :round-limit="$roundLimit" :set-code="$setCode" context="edit" />

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

        <form wire:submit="createTournament" class="space-y-6">
            <flux:input
                wire:model="name"
                label="Name"
            />

            <flux:input wire:model="scheduledDate" type="date" label="Datum des Spielabends (optional)" />

            <x-tournament-format-fields :format="$format" :sets="$sets" :table-preference="$tablePreference" :round-limit="$roundLimit" :set-code="$setCode" context="create" />

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
                    type="submit"
                >
                    Turnier erstellen
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>

<div>
    <section class="page-intro">
        <p class="eyebrow">{{ __('DEIN NÄCHSTES MATCH WARTET') }}</p>
        <div class="intro-row"><div><h1>{{ __('Zusammen an den Tisch.') }}</h1><p class="muted">{{ __('Turnier öffnen, mitspielen, Ergebnisse eintragen.') }}</p></div>
        @if(auth()->user()->is_admin)
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">{{ __('Turnier erstellen') }}</flux:button>
        @endif</div>
    </section>
    <div class="section-heading"><h2>{{ __('Turniere') }}</h2><label>{{ __('Sortieren') }} <select wire:change="sort($event.target.value)" aria-label="{{ __('Turniere sortieren') }}"><option value="scheduled_on" @selected($sortBy === 'scheduled_on')>{{ __('Spieltermin') }}</option><option value="created_at" @selected($sortBy === 'created_at')>{{ __('Erstellt') }}</option><option value="name" @selected($sortBy === 'name')>{{ __('Name') }}</option><option value="status" @selected($sortBy === 'status')>{{ __('Status') }}</option></select></label></div>
    <div class="event-filters">
        <flux:input wire:model.live.debounce.300ms="search" type="search" label="{{ __('Turnier suchen') }}" placeholder="{{ __('Name des Turniers') }}" maxlength="100" />
        <flux:select wire:model.live="status" label="{{ __('Status') }}"><option value="">{{ __('Alle Turniere') }}</option><option value="OPEN">{{ __('Anmeldung offen') }}</option><option value="IN_PROGRESS">{{ __('Wird gespielt') }}</option><option value="CLOSED">{{ __('Beendet') }}</option></flux:select>
    </div>
    <div class="tournament-grid" wire:loading.class="opacity-60">
        @forelse ($this->tournaments as $tournament)
            <article class="event-card">
                <div class="card-top"><span class="status-pill {{ strtolower($tournament->status->name) }}">{{ match($tournament->status) { App\Enums\Status::OPEN => __('Anmeldung offen'), App\Enums\Status::IN_PROGRESS => __('Wird gespielt'), default => __('Beendet') } }}</span><span class="muted">{{ $tournament->scheduled_on?->format('d.m.Y') ?? __('Termin offen') }}</span></div>
                @if($tournament->format === 'sealed' && $tournament->set_code)
                    <x-set-visual :code="$tournament->set_code" :name="$tournament->set_name" :context="'card-'.$tournament->id" />
                @endif
                <a class="event-title" href="{{ route('tournament', $tournament) }}" wire:navigate>{{ $tournament->name }}</a>
                <p class="muted">{{ match($tournament->format) { 'sealed' => __('Sealed'), 'commander' => __('Commander'), default => __('Constructed') } }}{{ $tournament->set_name ? ' · '.$tournament->set_name : '' }}</p>
                <p class="muted">{{ $tournament->group->name }} · {{ $tournament->users_count }} {{ __('Teilnehmende') }}</p>
                <div class="card-actions">
                    <a class="arena-button" href="{{ route('tournament', $tournament) }}" wire:navigate>{{ __('Turnier öffnen') }}</a>
                    @if($tournament->status === App\Enums\Status::OPEN && !$tournament->joined)
                        <a class="utility-button" href="{{ route('tournament', $tournament) }}#join" wire:navigate>{{ __('Mitspielen') }}</a>
                    @endif
                </div>
                @if(auth()->user()->is_admin)
                    <div class="admin-actions"><flux:button variant="ghost" wire:click="openEditModal({{ $tournament->id }})">{{ __('Bearbeiten') }}</flux:button><flux:button variant="ghost" wire:click="openDeleteModal({{ $tournament->id }})">{{ __('Löschen') }}</flux:button></div>
                @endif
            </article>
        @empty
            <div class="empty-state"><h2>{{ $search || $status ? __('Kein passendes Turnier.') : __('Platz für die nächste Spielrunde.') }}</h2><p>{{ $search || $status ? __('Versuche einen anderen Namen oder Status.') : __('Hier erscheinen eure Turniere, sobald die Organisation eines erstellt.') }}</p></div>
        @endforelse
    </div>
    <div class="pagination">{{ $this->tournaments->links() }}</div>
    <!-- Modals -->
    <flux:modal name="edit-tournament">
        @if($selectedTournament)
            <flux:heading size="lg">
                {{ __('Turnier bearbeiten') }}
            </flux:heading>

            <form wire:submit="editTournament" class="space-y-6">
                <flux:input wire:model="name" label="{{ __('Name') }}"/>
                <flux:input wire:model="scheduledDate" type="date" label="{{ __('Datum des Spielabends (optional)') }}" />
                <x-tournament-format-fields :format="$format" :sets="$sets" :table-preference="$tablePreference" :round-limit="$roundLimit" :set-code="$setCode" context="edit" />

                <flux:select wire:model="groupId" placeholder="{{ __('Gruppe auswählen...') }}">
                    @foreach($groups as $group)
                        <flux:select.option value="{{ $group->id }}">
                            {{ $group->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="primary">{{ __('Speichern') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    <flux:modal name="delete-tournament">
        @if($selectedTournament)
            <flux:heading size="lg">{{ __('Turnier löschen') }}</flux:heading>

            <div class="mb-5">
                {{ __('Möchtest du das Turnier') }}
                <strong>"{{ $selectedTournament->name }}"</strong>
                {{ __('wirklich löschen?') }}
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Abbrechen') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" wire:click="deleteTournament">
                    {{ __('Ja') }}
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
                label="{{ __('Name') }}"
            />

            <flux:input wire:model="scheduledDate" type="date" label="{{ __('Datum des Spielabends (optional)') }}" />

            <x-tournament-format-fields :format="$format" :sets="$sets" :table-preference="$tablePreference" :round-limit="$roundLimit" :set-code="$setCode" context="create" />

            <flux:select
                wire:model="groupId"
                placeholder="{{ __('Gruppe auswählen...') }}"
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
                        {{ __('Abbrechen') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    variant="primary"
                    type="submit"
                >
                    {{ __('Turnier erstellen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>

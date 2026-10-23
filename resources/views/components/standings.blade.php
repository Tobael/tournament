@props(['tournament'])
<div class="standings-list">
    @forelse($tournament->standings() as $standing)
        <div class="standing-row"><span class="place">{{ $standing['place'] ?? '–' }}</span><div class="min-w-0"><strong>{{ $standing['name'] }}</strong><p class="muted">{{ $standing['deck'] ?: 'Kein Deck angegeben' }} · {{ $standing['games'] }} Spiele</p></div><span class="points">{{ $standing['points'] }}<small>Punkte</small></span></div>
    @empty
        <p class="empty-state">Noch niemand dabei. Zeit für die erste Anmeldung!</p>
    @endforelse
</div>

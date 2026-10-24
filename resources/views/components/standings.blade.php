@props(['tournament'])
<div class="standings-list">
    @forelse($tournament->standings() as $standing)
        <div class="standing-row" data-leader="{{ ($standing['place'] ?? 0) === 1 && $standing['points'] > 0 ? 'true' : 'false' }}"><span class="place">{{ $standing['place'] ?? '–' }}</span><div class="min-w-0"><strong>{{ $standing['name'] }}</strong><p class="muted">{{ $standing['games'] }} Spiele</p></div><span class="points">{{ $standing['points'] }}<small>Punkte</small></span></div>
    @empty
        <p class="empty-state">Noch niemand dabei. Zeit für die erste Anmeldung!</p>
    @endforelse
</div>

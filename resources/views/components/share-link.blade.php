@props(['url', 'label', 'share' => false])
<div class="share-link" data-share-link>
    <input class="link-field" data-share-url readonly value="{{ $url }}" aria-label="{{ $label }}" onclick="this.focus(); this.select(); this.setSelectionRange(0, this.value.length)">
    <div class="share-actions">
        <button type="button" class="utility-button" data-copy-link>Link kopieren</button>
        @if($share)
            <button type="button" class="utility-button" data-share-link-button>Teilen ↗</button>
        @endif
    </div>
    <p class="muted" role="status" aria-live="polite" data-copy-message></p>
</div>

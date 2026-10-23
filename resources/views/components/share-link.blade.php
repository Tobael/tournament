@props(['url', 'label', 'share' => false])
<div class="share-link" x-data="{ url: @js($url), copied: false, message: '' }">
    <input class="link-field" readonly value="{{ $url }}" aria-label="{{ $label }}" x-on:click="$el.select()">
    <div class="share-actions">
        <button type="button" class="utility-button" x-on:click="try { await navigator.clipboard.writeText(url); copied = true; message = 'Link kopiert'; } catch { message = 'Bitte den Link im Feld markieren und kopieren.'; }" x-text="copied ? '✓ Kopiert' : 'Link kopieren'">Link kopieren</button>
        @if($share)
            <button type="button" class="utility-button" x-show="navigator.share" x-cloak x-on:click="try { await navigator.share({ title: 'Spielrunde', url }); } catch (error) { if (error.name !== 'AbortError') message = 'Bitte den Link kopieren.'; }">Teilen ↗</button>
        @endif
    </div>
    <p class="muted" role="status" x-text="message"></p>
</div>

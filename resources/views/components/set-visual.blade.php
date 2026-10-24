@props(['code', 'name', 'variant' => 'card', 'context' => 'event'])
@if($code && preg_match('/^[a-z0-9]{2,10}$/', $code))
    <figure class="set-visual set-visual-{{ $variant }}" data-set-visual data-artwork-url="{{ route('set.artwork', $code) }}" wire:key="set-visual-{{ $context }}-{{ $code }}" wire:ignore>
        <div class="set-art" data-set-art hidden>
            <img data-set-art-image alt="" decoding="async" loading="eager" referrerpolicy="no-referrer" />
            <figcaption class="set-art-credit"><span data-set-credit></span> · © Wizards of the Coast · <a data-set-source target="_blank" rel="noopener noreferrer">Scryfall ↗</a></figcaption>
        </div>
        <div class="set-identity">
            <span class="set-symbol"><img data-set-symbol src="{{ route('set.symbol', $code) }}" alt="" loading="eager" decoding="async" hidden /><span data-set-symbol-fallback>{{ strtoupper($code) }}</span></span>
            <span class="set-identity-text"><span class="eyebrow">DIE WELT EURES SEALED</span><strong>{{ $name ?: strtoupper($code) }}</strong><span class="set-code">{{ strtoupper($code) }}</span></span>
        </div>
    </figure>
@endif

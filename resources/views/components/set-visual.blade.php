@props(['code', 'name', 'variant' => 'card', 'context' => 'event'])
@if($code && preg_match('/^[a-z0-9]{2,10}$/', $code))
    <figure class="set-visual set-visual-{{ $variant }}" data-set-visual data-artwork-url="{{ $variant === 'card' ? '' : route('set.artwork', $code) }}" wire:key="set-visual-{{ $context }}-{{ $code }}" @if($variant !== 'header') wire:ignore @endif>
        <div class="set-art" data-set-art hidden wire:ignore>
            <img data-set-art-image alt="" decoding="async" loading="eager" referrerpolicy="no-referrer" />
        </div>
        <div class="set-visual-body">
        {{ $slot }}
        <div class="set-identity" wire:ignore>
            <span class="set-symbol"><img data-set-symbol src="{{ route('set.symbol', $code) }}" alt="" loading="eager" decoding="async" hidden /><span data-set-symbol-fallback>{{ strtoupper($code) }}</span></span>
            <span class="set-identity-text"><span class="eyebrow">SEALED · SET</span><strong>{{ $name ?: strtoupper($code) }}</strong><span class="set-code">{{ strtoupper($code) }}</span></span>
        </div>
        </div>
        <figcaption class="set-art-credit" data-set-caption hidden wire:ignore><span data-set-credit></span> · © Wizards of the Coast · <a data-set-source target="_blank" rel="noopener noreferrer">Scryfall ↗</a></figcaption>
    </figure>
@else
    {{ $slot }}
@endif

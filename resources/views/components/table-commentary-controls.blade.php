@props(['language' => false])
<div class="table-commentary-controls" data-commentary-controls>
    <button type="button" class="utility-button" data-commentary-toggle aria-pressed="false">{{ __('Ton aktivieren') }}</button>
    <details class="commentary-settings">
        <summary aria-label="{{ __('Treffer-Effekte einstellen') }}" title="{{ __('Treffer-Effekte einstellen') }}">♫</summary>
        <div class="commentary-settings-panel">
            <strong>{{ __('Treffer & Soundboard') }}</strong>
            <label class="commentary-checkbox"><input type="checkbox" data-commentary-popups checked> {{ __('Popup-Effekte') }}</label>
            <label class="commentary-checkbox"><input type="checkbox" data-commentary-clips checked> {{ __('Meme-Sounds') }}</label>
            <label>{{ __('Lautstärke') }}<input type="range" min="0" max="100" value="65" data-commentary-volume></label>
            <label>{{ __('Sound auswählen') }}<select data-commentary-sample>
                <option value="first-blood">First Blood</option><option value="emotional-damage">Emotional Damage</option>
                <option value="lotta-damage">That’s a Lotta Damage</option><option value="bruh">Bruh</option>
                <option value="fatality">Fatality</option><option value="wasted">Wasted</option>
            </select></label>
            <button type="button" class="utility-button" data-commentary-test>{{ __('Sound testen') }}</button>
            @if($language)<div><span>{{ __('Sprache') }}</span><x-language-switch /></div>@endif
            <p class="muted">{{ __('Kurze Effekte, lange Pausen. Ton und Popups lassen sich getrennt ausschalten.') }}</p>
        </div>
    </details>
</div>

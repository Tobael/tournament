@php
    $clientKeys = array_fill_keys(json_decode(file_get_contents(lang_path('client-keys.json')), true), true);
    $clientMessages = array_intersect_key(json_decode(file_get_contents(lang_path(app()->getLocale().'.json')), true), $clientKeys);
@endphp
<script id="app-translations" type="application/json">{!! json_encode(['locale' => app()->getLocale(), 'messages' => base64_encode(json_encode($clientMessages, JSON_UNESCAPED_UNICODE))], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

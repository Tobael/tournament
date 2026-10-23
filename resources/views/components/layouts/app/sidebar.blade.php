<!DOCTYPE html>
<html lang="de">
<head>@include('partials.head', ['arena' => true])</head>
<body class="arena-body">
    <header class="arena-header">
        <a class="arena-brand" href="{{ auth()->check() ? route('tournaments') : route('home') }}" wire:navigate><span class="brand-mark">✦</span> Spielrunde</a>
        <nav class="arena-nav" aria-label="Hauptnavigation">
            @auth
                <a href="{{ route('tournaments') }}" wire:navigate>Turniere</a>
                <a href="{{ route('profile.edit') }}" wire:navigate aria-label="Profil und Einstellungen">{{ auth()->user()->initials() }}</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Abmelden</button></form>
            @else
                <a href="{{ route('login') }}">Organisation</a>
            @endauth
        </nav>
    </header>
    {{ $slot }}
    @fluxScripts
</body>
</html>

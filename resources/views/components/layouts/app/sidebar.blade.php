<!DOCTYPE html>
<html lang="de" class="dark">
<head>@include('partials.head', ['arena' => true])</head>
<body class="arena-body">
    <header class="arena-header">
        <a class="arena-brand" href="{{ auth()->check() ? route('tournaments') : route('home', ['home' => 1]) }}" wire:navigate><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M7 7h16v21H7z" transform="rotate(-12 7 7)" stroke="currentColor" stroke-width="1.5" opacity=".5"/><rect x="9" y="4" width="16" height="22" rx="3" fill="currentColor"/><path d="m17 9 4 6-4 6-4-6 4-6Z" fill="#c6fb65"/></svg></span> Spielrunde</a>
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

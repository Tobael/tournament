<form method="POST" action="{{ route('language') }}" class="language-switch" aria-label="{{ __('Sprache') }}">
    @csrf
    <button type="submit" name="locale" value="de" lang="de" aria-label="Deutsch" aria-pressed="{{ app()->getLocale() === 'de' ? 'true' : 'false' }}">DE</button>
    <button type="submit" name="locale" value="en" lang="en" aria-label="English" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}">EN</button>
</form>

@php
    $current = app()->getLocale();
    $route = 'filament.'.filament()->getCurrentPanel()->getId().'.language';
    $names = ['en' => 'EN', 'fr' => 'FR'];
@endphp
<div class="sp-lang" role="group" aria-label="{{ __('Language') }}">
    @foreach (config('platform.locales') as $locale)
        <form method="POST" action="{{ route($route, ['locale' => $locale]) }}">
            @csrf
            <button type="submit" lang="{{ $locale }}" @class(['sp-lang__btn', 'is-active' => $locale === $current]) aria-pressed="{{ $locale === $current ? 'true' : 'false' }}" title="{{ $locale === 'fr' ? 'Français' : 'English' }}">{{ $names[$locale] ?? strtoupper($locale) }}</button>
        </form>
    @endforeach
</div>

@if (\App\Support\Branding::showPoweredBy())
    <p class="sp-powered-by">
        {{ __('Powered by :name', ['name' => \App\Support\Branding::name()]) }}@if (\App\Support\Branding::tagline())<span class="sp-powered-by__tagline"> · {{ \App\Support\Branding::tagline() }}</span>@endif
    </p>
@endif

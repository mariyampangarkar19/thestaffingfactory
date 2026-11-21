@php
    $user = filament()->auth()->user();
    $asked = request()->boolean('tour');
@endphp
@if (! request()->routeIs('*.auth.*') && \App\Support\WelcomeTour::shouldOpen($user, $asked))
    @php
        $panel = filament()->getCurrentPanel()->getId();
        $agency = app(\App\Tenancy\TenantContext::class)->tenant()?->name ?? \App\Support\Branding::name();
        $steps = \App\Support\WelcomeTour::steps($panel, $user, $agency);
        $doneUrl = route('filament.'.$panel.'.tour.done');
    @endphp
    <div
        x-data="{
            open: true, step: 0, total: {{ count($steps) }},
            finish() {
                this.open = false;
                fetch(@js($doneUrl), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } });
            },
        }"
        x-show="open"
        x-on:keydown.escape.window="finish()"
        x-init="$nextTick(() => $refs.next?.focus())"
        class="sp-tour"
        role="dialog" aria-modal="true" aria-labelledby="sp-tour-title"
    >
        <div class="sp-tour__backdrop" x-on:click="finish()"></div>
        <div class="sp-tour__card">
            @foreach ($steps as $i => $step)
                <section x-show="step === {{ $i }}" @if ($i > 0) x-cloak @endif class="sp-tour__step">
                    <span class="sp-tour__icon"><x-filament::icon :icon="$step['icon']" class="sp-tour__icon-svg" /></span>
                    <h2 @if ($i === 0) id="sp-tour-title" @endif class="sp-tour__title">{{ $step['title'] }}</h2>
                    <p class="sp-tour__body">{{ $step['body'] }}</p>
                </section>
            @endforeach

            <div class="sp-tour__dots" aria-hidden="true">
                @foreach ($steps as $i => $step)
                    <i :class="{ 'is-on': step === {{ $i }} }"></i>
                @endforeach
            </div>

            <div class="sp-tour__actions">
                <button type="button" class="sp-tour__skip" x-on:click="finish()" x-show="step < total - 1">{{ __('Skip') }}</button>
                <span class="sp-tour__spacer"></span>
                <button type="button" class="sp-tour__back" x-on:click="step--" x-show="step > 0" x-cloak>{{ __('Back') }}</button>
                <button type="button" class="sp-tour__next" x-ref="next" x-on:click="step < total - 1 ? step++ : finish()"
                    x-text="step < total - 1 ? @js(__('Next')) : @js(__('Get started'))">{{ __('Next') }}</button>
            </div>
        </div>
    </div>
@endif

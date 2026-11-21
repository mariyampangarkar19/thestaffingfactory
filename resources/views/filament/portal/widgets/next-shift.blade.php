<x-filament-widgets::widget class="sp-next">
    @if ($toSubmit > 0)
        <a href="{{ $timesheetsUrl }}" class="sp-next__nudge">
            <x-filament::icon icon="heroicon-o-clock" class="sp-next__nudge-icon" />
            <span>
                <strong>{{ trans_choice('{1} 1 shift is waiting for your hours|[2,*] :count shifts are waiting for your hours', $toSubmit, ['count' => $toSubmit]) }}</strong>
                <span>{{ __('Submit them today so you are paid on time.') }}</span>
            </span>
            <span class="sp-next__nudge-go">{{ __('Submit hours') }}</span>
        </a>
    @endif

    @if ($next)
        <article class="sp-next__card" aria-labelledby="sp-next-title">
            <p class="sp-next__eyebrow">{{ $next['started'] ? __('On shift now') : __('Your next shift') }} · {{ $next['when'] }}</p>
            <h2 id="sp-next-title" class="sp-next__day">{{ $next['day'] }}</h2>
            <p class="sp-next__hours">{{ $next['hours'] }}</p>
            <dl class="sp-next__facts">
                <div><dt>{{ __('Position') }}</dt><dd>{{ $next['position'] }}</dd></div>
                <div><dt>{{ __('Event') }}</dt><dd>{{ $next['event'] }} · {{ $next['client'] }}</dd></div>
                <div><dt>{{ __('Place') }}</dt><dd>{{ $next['place'] ?: '—' }}</dd></div>
                @if ($next['notes'])
                    <div><dt>{{ __('Instructions') }}</dt><dd>{{ $next['notes'] }}</dd></div>
                @endif
            </dl>
            <a href="{{ $assignmentsUrl }}" class="sp-next__link">{{ __('See all my assignments') }}</a>
        </article>
    @else
        <article class="sp-next__card sp-next__card--empty">
            <p class="sp-next__eyebrow">{{ __('Your next shift') }}</p>
            <h2 class="sp-next__day">{{ __('Nothing planned yet') }}</h2>
            <p class="sp-next__hours">{{ __('The agency will put your next assignment here. Keep your availability up to date in your profile.') }}</p>
        </article>
    @endif
</x-filament-widgets::widget>

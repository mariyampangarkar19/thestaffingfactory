@props(['grid'])

<div class="sp-cal-toolbar">
    <div class="sp-cal-nav">
        <x-filament::icon-button icon="heroicon-o-chevron-left" color="gray" wire:click="showMonth('{{ $grid->previous() }}')" :label="__('Previous month')" />
        <x-filament::icon-button icon="heroicon-o-chevron-right" color="gray" wire:click="showMonth('{{ $grid->next() }}')" :label="__('Next month')" />
        <h2 class="sp-cal-title">{{ $grid->title() }}</h2>
    </div>
    <x-filament::button color="gray" outlined size="sm" wire:click="showMonth(null)">{{ __('Today') }}</x-filament::button>
</div>

<div class="sp-cal" wire:loading.class="is-loading">
    @foreach ($grid->weekdays() as $weekday)
        <div class="sp-cal-head">{{ $weekday }}</div>
    @endforeach

    @foreach ($grid->days() as $day)
        <div @class(['sp-cal-day', 'is-other' => ! $day['inMonth'], 'is-today' => $day['isToday'], 'is-empty' => $day['events'] === []])>
            <span class="sp-cal-date" title="{{ $day['label'] }}">{{ $day['date']->day }}</span>
            <div class="sp-cal-events">
                <span class="sp-cal-daylabel">{{ $day['label'] }}</span>
                @foreach ($day['events'] as $event)
                    @if ($event['url'])
                        <a href="{{ $event['url'] }}" class="sp-cal-event {{ $event['class'] }}" title="{{ $event['title'] }}"><strong>{{ $event['time'] }}</strong> {{ $event['label'] }}</a>
                    @else
                        <span class="sp-cal-event {{ $event['class'] }}" title="{{ $event['title'] }}"><strong>{{ $event['time'] }}</strong> {{ $event['label'] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
</div>

@if (collect($grid->days())->every(fn ($day) => $day['events'] === []))
    <p class="sp-cal-none">{{ __('Nothing scheduled this month.') }}</p>
@endif

<x-filament-panels::page>
    @include('filament.partials.month-calendar', ['grid' => $this->grid()])

    <div class="sp-legend">
        <span><i class="sp-cal-event"></i>{{ __('Fully staffed') }}</span>
        <span><i class="sp-cal-event is-understaffed"></i>{{ __('Still needs people') }}</span>
        <span><i class="sp-cal-event is-done"></i>{{ __('Completed') }}</span>
    </div>
</x-filament-panels::page>

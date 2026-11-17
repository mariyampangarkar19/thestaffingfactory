<x-filament-panels::page>
    <x-filament::section :heading="__('Period')" :description="__('Dates are in the agency time zone. One year at most.')">
        <div class="sp-period">
            <label>
                <span>{{ __('From') }}</span>
                <x-filament::input.wrapper :valid="! $errors->has('from')">
                    <x-filament::input type="date" wire:model.live="from" />
                </x-filament::input.wrapper>
            </label>
            <label>
                <span>{{ __('To') }}</span>
                <x-filament::input.wrapper :valid="! $errors->has('to')">
                    <x-filament::input type="date" wire:model.live="to" />
                </x-filament::input.wrapper>
            </label>
        </div>
        @error('to') <p class="sp-field-error">{{ $message }}</p> @enderror
        @error('from') <p class="sp-field-error">{{ $message }}</p> @enderror
    </x-filament::section>

    <div class="sp-report-grid">
        @foreach ($this->reports() as $key => $report)
            <x-filament::section :heading="$report->title()" :description="$report->description()">
                <div class="sp-report-actions">
                    <x-filament::button tag="a" icon="heroicon-o-arrow-down-tray" :href="$this->exportUrl($key, 'xlsx')" :disabled="$errors->any()">
                        {{ __('Download Excel') }}
                    </x-filament::button>
                    <x-filament::button tag="a" color="gray" outlined icon="heroicon-o-printer" :href="$this->exportUrl($key, 'print')" target="_blank" rel="noopener">
                        {{ __('Print or save as PDF') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>

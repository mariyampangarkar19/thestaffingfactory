<x-filament-widgets::widget class="sp-today">
    <div class="sp-today__head">
        <div>
            <p class="sp-today__date">{{ $today }}</p>
            <h2 class="sp-today__greeting">{{ $greeting }}</h2>
        </div>
        @if ($shortcuts)
            <nav class="sp-today__shortcuts" aria-label="{{ __('Shortcuts') }}">
                @foreach ($shortcuts as $shortcut)
                    <a href="{{ $shortcut['url'] }}" class="sp-chip">
                        <x-filament::icon :icon="$shortcut['icon']" class="sp-chip__icon" />
                        <span>{{ $shortcut['label'] }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </div>

    <section class="sp-attn" aria-labelledby="sp-attn-title">
        <h3 id="sp-attn-title" class="sp-attn__title">{{ __('Needs your attention') }}</h3>

        @forelse ($items as $item)
            <a href="{{ $item->url }}" class="sp-attn__item sp-attn__item--{{ $item->tone }}">
                <span class="sp-attn__icon"><x-filament::icon :icon="$item->icon" class="sp-attn__icon-svg" /></span>
                <span class="sp-attn__count">{{ $item->count }}</span>
                <span class="sp-attn__text">
                    <strong>{{ $item->title }}</strong>
                    <span>{{ $item->hint }}</span>
                </span>
                <span class="sp-attn__go">{{ $item->action }} <x-filament::icon icon="heroicon-m-arrow-right" class="sp-attn__arrow" /></span>
            </a>
        @empty
            <div class="sp-attn__empty">
                <x-filament::icon icon="heroicon-o-check-circle" class="sp-attn__empty-icon" />
                <div>
                    <strong>{{ __('All caught up') }}</strong>
                    <span>{{ __('Nothing is waiting for you right now.') }}</span>
                </div>
            </div>
        @endforelse
    </section>
</x-filament-widgets::widget>

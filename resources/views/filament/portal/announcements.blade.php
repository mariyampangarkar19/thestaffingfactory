<x-filament-widgets::widget>
    @php $items = $this->items(); @endphp
    @if ($items->isNotEmpty())
        <x-filament::section :heading="__('Announcements')">
            <ul class="sp-announcements">
                @foreach ($items as $item)
                    <li>
                        <div class="sp-announcement-head">
                            <strong>{{ $item['title'] }}</strong>
                            <span>{{ $item['date'] }}</span>
                        </div>
                        <p>{{ $item['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>

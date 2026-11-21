@php
    $agency = app(\App\Tenancy\TenantContext::class)->tenant()?->name ?? \App\Support\Branding::name();
    $portal = filament()->getCurrentPanel()?->getId() === 'portal';
    $points = $portal
        ? [__('See where and when you work next'), __('Submit your hours from your phone'), __('Follow every payment until it is paid')]
        : [__('Customer requests from the website, phone and WhatsApp in one list'), __('Assign approved freelancers, with clash and availability checks'), __('Approve hours and pay freelancers by mobile money or bank')];
@endphp
<aside class="sp-auth-aside" aria-label="{{ $agency }}">
    <div class="sp-auth-aside__brand">
        <span class="sp-auth-aside__mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($agency, 0, 1)) }}</span>
        <span>{{ $agency }}</span>
    </div>

    <div class="sp-auth-aside__body">
        <p class="sp-auth-aside__eyebrow">{{ $portal ? __('Freelancer portal') : __('Agency workspace') }}</p>
        <h2 class="sp-auth-aside__title">{{ $portal ? __('Your work, in your pocket.') : __('Every shift staffed. Every hour accounted for.') }}</h2>
        <ul class="sp-auth-aside__points">
            @foreach ($points as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>

        {{-- A sample shift, so first-time visitors see what the work looks like. --}}
        <div class="sp-auth-aside__sample" aria-hidden="true">
            <div class="sp-auth-aside__sample-head">
                <span>{{ __('Gala dinner') }}</span>
                <span class="sp-auth-aside__pill">{{ __('Confirmed') }}</span>
            </div>
            <p>{{ __('Saturday') }} · 17:00 – 23:00 · Bonanjo, Douala</p>
            <div class="sp-auth-aside__bar"><i style="width:100%"></i></div>
            <p class="sp-auth-aside__muted">{{ $portal ? __('Waiter · 7 h 30 · paid by mobile money') : __('Waiters 3 / 3 · Hostesses 2 / 2') }}</p>
        </div>
    </div>

    @if (\App\Support\Branding::showPoweredBy())
        <p class="sp-auth-aside__foot">{{ __('Powered by :name', ['name' => \App\Support\Branding::name()]) }}@if (\App\Support\Branding::tagline()) · {{ \App\Support\Branding::tagline() }}@endif</p>
    @endif
</aside>

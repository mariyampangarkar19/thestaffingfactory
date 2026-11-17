<?php

namespace App\Providers\Filament\Concerns;

use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\SetDisplayTimezone;
use App\Http\Middleware\SetLocale;
use App\Support\Branding;
use App\Tenancy\TenantContext;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * What every panel shares: brand theme, font, product branding (config/branding.php), and the middleware stack.
 * $first is the middleware that decides which database role and agency the request runs as; it must come first.
 */
trait ConfiguresPanel
{
    /** Teal scale built around the brand teal #1C97A6 (500). */
    private const BRAND_TEAL = [
        50 => '#e9f7f9', 100 => '#d3eef2', 200 => '#a6dde5', 300 => '#72c8d4', 400 => '#3fb0bf', 500 => '#1c97a6',
        600 => '#107f8d', 700 => '#006876', 800 => '#00525d', 900 => '#003c45', 950 => '#00272d',
    ];

    protected function configure(Panel $panel, string $first): Panel
    {
        return $panel
            ->viteTheme('resources/css/filament/theme.css')
            ->font('Roboto', provider: LocalFontProvider::class) // self-hosted in the theme; no third-party font requests
            ->colors(['primary' => self::BRAND_TEAL])
            ->darkMode(false)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->favicon(Branding::faviconUrl())
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_END, fn (): View => view('filament.partials.powered-by'))
            ->middleware([
                $first,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                SetDisplayTimezone::class,
            ])
            // Livewire's follow-up requests already pass through the "web" group (cookies, session, CSRF). Only these
            // are run again there, so every action is still tied to the agency in the address and its language and time zone.
            ->middleware([$first, AuthenticateSession::class, SetLocale::class, SetDisplayTimezone::class], isPersistent: true);
    }

    protected function agencyName(): string
    {
        return app(TenantContext::class)->tenant()?->name ?? Branding::name();
    }
}

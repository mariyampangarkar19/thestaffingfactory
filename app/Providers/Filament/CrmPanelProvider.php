<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use App\Tenancy\TenantContext;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Agency CRM for agency staff, on the agency's own host. MFA required for every staff account.
 */
class CrmPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('crm')
            ->path('crm')
            ->authGuard('web')
            ->login()
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: true)
            ->colors(['primary' => Color::Teal])
            ->brandName(fn () => app(TenantContext::class)->tenant()?->name ?? config('platform.name'))
            ->discoverResources(in: app_path('Filament/Crm/Resources'), for: 'App\Filament\Crm\Resources')
            ->discoverPages(in: app_path('Filament/Crm/Pages'), for: 'App\Filament\Crm\Pages')
            ->pages([Dashboard::class])
            ->middleware([
                ResolveTenant::class,
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
            ], isPersistent: true)
            ->authMiddleware([Authenticate::class]);
    }
}

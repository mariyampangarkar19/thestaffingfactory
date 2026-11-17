<?php

namespace App\Providers\Filament;

use App\Http\Middleware\UsePlatformConnection;
use App\Providers\Filament\Concerns\ConfiguresPanel;
use App\Support\Branding;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Super-admin: agencies and (next stage) subscriptions. Only on admin.{base_domain}, own guard, MFA required.
 */
class PlatformPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configure($panel, UsePlatformConnection::class)
            ->id('platform')
            ->path('platform')
            ->authGuard('platform')
            ->login()
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()], isRequired: true)
            ->brandName(fn (): string => Branding::name().' · '.__('Platform'))
            ->brandLogo(Branding::logoUrl())
            ->brandLogoHeight(Branding::logoHeight())
            ->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\Filament\Platform\Resources')
            ->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\Filament\Platform\Pages')
            ->pages([Dashboard::class])
            ->authMiddleware([Authenticate::class]);
    }
}

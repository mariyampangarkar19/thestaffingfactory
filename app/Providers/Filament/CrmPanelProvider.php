<?php

namespace App\Providers\Filament;

use App\Filament\Crm\Pages\Dashboard;
use App\Http\Middleware\ResolveTenant;
use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Agency Portal (CRM) for agency staff, on the agency's own host.
 */
class CrmPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configure($panel, ResolveTenant::class)
            ->default()
            ->id('crm')
            ->path('crm')
            ->authGuard('web')
            ->login()
            ->passwordReset()
            ->profile()
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()],
                isRequired: (bool) config('platform.require_staff_mfa'),
            )
            ->brandName(fn () => $this->agencyName())
            ->sidebarWidth('17rem')
            ->navigationGroups([
                NavigationGroup::make(fn () => __('Operations')),
                NavigationGroup::make(fn () => __('People')),
                NavigationGroup::make(fn () => __('Finance')),
                NavigationGroup::make(fn () => __('Records')),
                NavigationGroup::make(fn () => __('Settings')),
            ])
            ->discoverResources(in: app_path('Filament/Crm/Resources'), for: 'App\Filament\Crm\Resources')
            ->discoverPages(in: app_path('Filament/Crm/Pages'), for: 'App\Filament\Crm\Pages')
            ->discoverWidgets(in: app_path('Filament/Crm/Widgets'), for: 'App\Filament\Crm\Widgets')
            ->pages([Dashboard::class])
            ->authMiddleware([Authenticate::class]);
    }
}

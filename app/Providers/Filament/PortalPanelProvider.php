<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Portal\Pages\Dashboard;
use App\Http\Middleware\ResolveTenant;
use App\Providers\Filament\Concerns\ConfiguresPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Freelancer Portal: a freelancer sees only their own profile, assignments, timesheets and payments.
 */
class PortalPanelProvider extends PanelProvider
{
    use ConfiguresPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configure($panel, ResolveTenant::class)
            ->id('portal')
            ->path('portal')
            ->authGuard('web')
            ->login(Login::class)
            ->passwordReset()
            ->topNavigation()
            ->maxContentWidth(Width::FiveExtraLarge)
            ->databaseNotifications()
            ->brandName(fn () => $this->agencyName())
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): View => view('filament.partials.powered-by'))
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\Filament\Portal\Widgets')
            ->pages([Dashboard::class])
            ->authMiddleware([Authenticate::class]);
    }
}
